<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Throwable;
use RuntimeException;
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

/**
 * Integrates DPD Group's "NST Shipping API" (https://nst-preprod.dpsin.dpdgroup.com / https://shipping.dpdgroup.com).
 */
#[WithMonologChannel('api.parcel.dpd')]
class DpdParcel implements ParcelServiceInterface
{
    private const PROD_URL = 'https://shipping.dpdgroup.com/api/v1.1/';
    private const SANDBOX_URL = 'https://nst-preprod.dpsin.dpdgroup.com/api/v1.1/';
    private const MAIN_SERVICE_CODE = '101';

    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface  $httpClient,
        private readonly LoggerInterface      $logger,
        private readonly PurchasePriceFactory $purchasePriceFactory,
        private readonly CurrencyRepository   $currencyRepository,
        #[Autowire(param: 'greendot_eshop.parcel.dpd.customer_id')]
        private readonly string               $customerId,
        #[Autowire(param: 'greendot_eshop.parcel.dpd.sender_address_id')]
        private readonly string               $senderAddressId,
        #[Autowire(param: 'kernel.environment')]
        string                                $environment,
        #[Autowire(param: 'greendot_eshop.parcel.dpd.enabled')]
        private readonly bool                 $enabled = false,
    )
    {
        $this->baseUrl = in_array($environment, ['test', 'dev'], true) ? self::SANDBOX_URL : self::PROD_URL;
    }

    public function createParcel(Purchase $purchase): string
    {
        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            throw new InvalidArgumentException('No transportation set for purchase');
        }

        $body = $this->prepareShipmentData($purchase);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . 'shipments', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $transportation->getSecretKey(),
                ],
                'json' => $body,
            ]);

            $data = $response->toArray(false);

            $shipmentId = $data['shipmentResults'][0]['shipmentId'] ?? null;
            $mpsId = $data['shipmentResults'][0]['mpsId'] ?? null;
            if ($shipmentId === null || $mpsId === null) {
                $this->logger->error('DPD API error on createParcel', [
                    'purchaseId' => $purchase->getId(),
                    'response' => $data,
                ]);
                throw new RuntimeException('DPD createParcel failed: no shipmentId/mpsId in response');
            }

            $purchase->setShipmentId((string)$shipmentId);

            return (string)$mpsId;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('DPD HTTP exception on createParcel', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getParcelStatus(Purchase $purchase): ParcelStatusInfoDto
    {
        $transportation = $purchase->getTransportation();
        $jwt = $transportation?->getSecretKey() ?? '';
        $shipmentId = (int)$purchase->getShipmentId();

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . 'shipments/' . $shipmentId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt,
                ],
            ]);

            $data = $response->toArray(false);
            $shipment = $data['shipment'] ?? null;
            if ($shipment === null) {
                throw new RuntimeException('DPD getParcelStatus failed: shipment not found in response');
            }

            $statusCode = (int)($shipment['status'] ?? 0);
            $updateDate = $shipment['updateDate'] ?? null;
            $updateTime = $shipment['updateTime'] ?? null;
            $occurredAt = ($updateDate && $updateTime)
                ? DateTimeImmutable::createFromFormat('YmdHis', $updateDate . $updateTime) ?: null
                : null;

            return new ParcelStatusInfoDto(
                state: $this->mapStatusCode($statusCode),
                details: ['status' => $statusCode],
                occurredAt: $occurredAt,
            );
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('DPD HTTP exception on getParcelStatus', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function supports(TransportationAPI $transportationAPI): bool
    {
        return $this->enabled && $transportationAPI === TransportationAPI::DPD;
    }

    private function prepareShipmentData(Purchase $purchase): array
    {
        $client = $purchase->getClient();
        $address = $purchase->getPurchaseAddress();
        $country = $address->getShipCountry() ?? $address->getCountry();

        $currency = match (strtolower((string)$country)) {
            'sk'    => 'EUR',
            default => 'CZK',
        };

        $currencyEntity = $currency === 'EUR'
            ? $this->currencyRepository->findOneBy(['isDefault' => false])
            : $this->currencyRepository->findOneBy(['isDefault' => true]);

        $priceCalculator = $this->purchasePriceFactory->create($purchase, $currencyEntity);

        $isCod = $purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY;
        $codAmount = $isCod
            ? (clone $priceCalculator)
                ->setVatCalculationType(VatCalculationType::WithVAT)
                ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                ->setVoucherCalculationType(VoucherCalculationType::WithVoucher)
                ->getPrice(true)
            : null;

        $receiver = [
            'name' => $address->getShipName() ?? $client->getName(),
            'name2' => $address->getShipSurname() ?? $client->getSurname(),
            'companyName' => $address->getShipCompany() ?? $address->getCompany(),
            'street' => $address->getShipStreet() ?? $address->getStreet(),
            'city' => $address->getShipCity() ?? $address->getCity(),
            'zipCode' => preg_replace('/\s+/', '', (string)($address->getShipZip() ?? $address->getZip())),
            'countryCode' => strtoupper((string)$country),
            'contactName' => trim(($client->getName() ?? '') . ' ' . ($client->getSurname() ?? '')),
            'contactEmail' => $client->getMail(),
            'contactPhone' => $client->getPhone(),
        ];

        $weight = 1;

        $shipment = [
            'numOrder' => 1,
            'senderAddressId' => (int)$this->senderAddressId,
            'receiver' => $receiver,
            'parcels' => [
                [
                    'weight' => $weight,
                    'reference1' => (string)$purchase->getId(),
                ],
            ],
            'reference1' => (string)$purchase->getId(),
            'saveMode' => 'printed',
            'printFormat' => 'PDF',
        ];

        $shipment['service'] = [
            'mainServiceCode' => self::MAIN_SERVICE_CODE,
        ];

        if ($codAmount !== null) {
            $shipment['service']['additionalService'] = [
                'cod' => [
                    'amount' => (string)$codAmount,
                    'currency' => $currency,
                    'paymentType' => 'Cash',
                    'reference' => (string)$purchase->getId(),
                    'split' => 'Even',
                ],
            ];
        }

        return [
            'customerId' => $this->customerId,
            'shipments' => [$shipment],
        ];
    }

    /**
     * GetExternalShipmentDTOV2.status only describes the shipment *record's* lifecycle
     * (-1 Cancelled, 0 Draft, 1 With pickup, 2 Printed) — it is NOT courier delivery
     * tracking. There is no documented value for "in transit", "out for delivery" or
     * "delivered", so this endpoint can never report DELIVERED/NOT_PICKED_UP/READY_FOR_PICKUP.
     * A real tracking source (separate API or webhook) is still needed for that; see
     * the note in DpdParcelTest and the plan for follow-up.
     */
    private function mapStatusCode(int $code): ParcelDeliveryStateEnum
    {
        return match ($code) {
            -1      => ParcelDeliveryStateEnum::CANCELLED,
            0, 1    => ParcelDeliveryStateEnum::RECEIVED_DATA,
            2       => ParcelDeliveryStateEnum::IN_TRANSIT,
            default => ParcelDeliveryStateEnum::RECEIVED_DATA,
        };
    }
}
