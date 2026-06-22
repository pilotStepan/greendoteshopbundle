<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Throwable;
use RuntimeException;
use SimpleXMLElement;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Parcel\ParcelStatusInfoDto;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

#[WithMonologChannel('api.parcel.packetery')]
class PacketeryParcel implements ParcelServiceInterface
{
    private const API_URL = 'https://www.zasilkovna.cz/api/rest';

    public function __construct(
        private readonly HttpClientInterface  $httpClient,
        private readonly LoggerInterface      $logger,
        private readonly PurchasePriceFactory $purchasePriceFactory,
        private readonly CurrencyRepository   $currencyRepository,
        #[Autowire(param: 'greendot_eshop.parcel.packeta.eshop_name')]
        private readonly string               $eshopName,
        #[Autowire(param: 'greendot_eshop.parcel.packeta.enabled')]
        private readonly bool                 $enabled = false,
    ) {}

    public function createParcel(Purchase $purchase): string
    {
        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            throw new InvalidArgumentException('No transportation set for purchase');
        }

        $xmlBody = $this->buildXml('createPacket', $this->prepareParcelData($purchase));

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => $xmlBody,
            ]);

            $xml = simplexml_load_string($response->getContent(false));

            if ((string)$xml->status !== 'ok') {
                $rawResponse = $response->getContent(false);
                $this->logger->error('Packeta API error on createPacket', [
                    'purchaseId' => $purchase->getId(),
                    'response' => $rawResponse,
                ]);
                throw new RuntimeException("Packeta createPacket failed: $rawResponse");
            }

            return (string)$xml->result->barcode;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Packeta HTTP exception on createPacket', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getParcelStatus(Purchase $purchase): ParcelStatusInfoDto
    {
        $transportation = $purchase->getTransportation();
        $apiPassword = $transportation?->getSecretKey() ?? '';
        $packetId = $purchase->getTransportNumber();

        $xmlBody = $this->buildXml('packetStatus', [
            'apiPassword' => $apiPassword,
            'packetId' => $packetId,
        ]);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => $xmlBody,
            ]);

            $xml = simplexml_load_string($response->getContent(false));

            if ((string)$xml->status !== 'ok') {
                $rawResponse = $response->getContent(false);
                $this->logger->error('Packeta API error on packetStatus', [
                    'purchaseId' => $purchase->getId(),
                    'response' => $rawResponse,
                ]);
                throw new RuntimeException("Packeta packetStatus failed: $rawResponse");
            }

            $statusCode = (int)$xml->result->statusCode;
            $codeText = (string)$xml->result->codeText;
            $dateTime = isset($xml->result->dateTime)
                ? new DateTimeImmutable((string)$xml->result->dateTime)
                : null;

            return new ParcelStatusInfoDto(
                state: $this->mapStatusCode($statusCode),
                details: ['statusCode' => $statusCode, 'codeText' => $codeText],
                occurredAt: $dateTime,
            );
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Packeta HTTP exception on packetStatus', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function supports(TransportationAPI $transportationAPI): bool
    {
        return $this->enabled && $transportationAPI === TransportationAPI::PACKETA;
    }

    private function prepareParcelData(Purchase $purchase): array
    {
        $transportation = $purchase->getTransportation();
        $client = $purchase->getClient();
        $address = $purchase->getPurchaseAddress();
        $country = $address->getShipCountry() ?? $address->getCountry();

        $currency = match ($country) {
            'sk'    => 'EUR',
            default => 'CZK',
        };

        $currencyEntity = $currency === 'EUR'
            ? $this->currencyRepository->findOneBy(['isDefault' => false])
            : $this->currencyRepository->findOneBy(['isDefault' => true]);

        $priceCalculator = $this->purchasePriceFactory->create($purchase, $currencyEntity);

        $value = (clone $priceCalculator)
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
            ->getPrice()
        ;

        $isCod = $purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY;
        $cod = $isCod
            ? (clone $priceCalculator)
                ->setVatCalculationType(VatCalculationType::WithVAT)
                ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                ->setVoucherCalculationType(VoucherCalculationType::WithVoucher)
                ->getPrice(true)
            : null;

        $branch = $purchase->getBranch();
        $addressId = $branch !== null
            ? (int)str_replace('packeta_', '', $branch->getProviderId())
            : (int)$transportation->getToken();

        // TODO: derive weight from order items
        $weight = 2;

        $packetAttributes = [
            'number' => (string)$purchase->getId(),
            'name' => $client->getName(),
            'surname' => $client->getSurname(),
            'email' => $client->getMail(),
            'phone' => $client->getPhone(),
            'addressId' => $addressId,
            'value' => $value,
            'currency' => $currency,
            'weight' => $weight,
            'eshop_id' => $this->eshopName,
        ];

        if ($cod !== null) {
            $packetAttributes['cod'] = $cod;
        }

        if ($branch === null) {
            $packetAttributes['street'] = $address->getShipStreet() ?? $address->getStreet();
            $packetAttributes['city'] = $address->getShipCity() ?? $address->getCity();
            $packetAttributes['zip'] = $address->getShipZip() ?? $address->getZip();
        }

        return [
            'apiPassword' => $transportation->getSecretKey(),
            'packetAttributes' => $packetAttributes,
        ];
    }

    private function mapStatusCode(int $code): ParcelDeliveryStateEnum
    {
        return match ($code) {
            1       => ParcelDeliveryStateEnum::RECEIVED_DATA,
            2, 3, 4 => ParcelDeliveryStateEnum::IN_TRANSIT,
            5       => ParcelDeliveryStateEnum::READY_FOR_PICKUP,
            7       => ParcelDeliveryStateEnum::DELIVERED,
            8       => ParcelDeliveryStateEnum::NOT_PICKED_UP,
            default => ParcelDeliveryStateEnum::CANCELLED,
        };
    }

    private function buildXml(string $rootElement, array $data): string
    {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><$rootElement/>");
        $this->arrayToXml($data, $xml);
        return $xml->asXML();
    }

    private function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value, ENT_XML1));
            }
        }
    }
}
