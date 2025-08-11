<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Exception;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Enum\TransportationAPI;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PacketeryParcel implements ParcelServiceInterface
{
    private const API_URL = 'https://www.zasilkovna.cz/api/rest';

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly LoggerInterface        $logger,
        private readonly PurchasePriceFactory   $purchasePriceFactory,
        private readonly CurrencyRepository     $currencyRepository,
    ) {}

    public function createParcel(Purchase $purchase): ?string
    {
        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            return null;
        }

        $requestData = $this->prepareParcelData($purchase);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'auth_bearer' => $transportation->getToken(),
                'body' => $this->createXmlRequest('createPacket', $requestData),
            ]);

            $data = $response->toArray();

            if (isset($data['packetId'])) {
                return $data['packetId'];
            }

            $this->logger->error('Failed to create parcel', [
                'purchaseId' => $purchase->getId(),
                'response' => $data,
            ]);
            return null;
        } catch (Exception $e) {
            $this->logger->error('Exception when creating parcel', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getParcelStatus(Purchase $purchase): ?array
    {
        return [];
    }

    private function prepareParcelData(Purchase $purchase): array
    {
        $client = $purchase->getClient();

        $czk = $this->currencyRepository->findOneBy(['isDefault' => true]);

        $purchasePriceCalculator = $this->purchasePriceFactory->create($purchase, $czk);

        // TODO: check that calculation types for value and cod correct

        // calculate parcel value
        $value = (clone $purchasePriceCalculator)
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
            ->getPrice();


        // calculate cash on delivery (null if cash on delivery payment not wanted)
        $cod = $purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY
            ? $purchasePriceCalculator
                ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                ->setVoucherCalculationType(VoucherCalculationType::WithVoucher)
                ->getPrice()
            : null;

        // TODO: make weight, is required
        
        return  [
            'apiPassword' => $purchase->getTransportation()->getSecretKey(),
            'packetAttributes' => [
                'number' => (string) $purchase->getId(),
                'name' => $client->getName(),
                'surname' => $client->getSurname(),
                'email' => $client->getMail(),
                'phone' => $client->getPhone(),
                'addressId' => $purchase->getTransportation()->getToken(),
                'value' => $value, 
                'eshop' => 'yogashop',
                ...($cod !== null ? ['cod' => $cod] : [])
                
            ],
        ];
    }

    private function createXmlRequest(string $method, array $data): string
    {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><$method></$method>");
        $this->arrayToXml($data, $xml);
        return $xml->asXML();
    }

    private function arrayToXml(array $data, SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }

    public function supports(TransportationAPI $transportationAPI): bool
    {
        return $transportationAPI === TransportationAPI::PACKETA;
    }
}