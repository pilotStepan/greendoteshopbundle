<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Throwable;
use SimpleXMLElement;
use RuntimeException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Dto\ParcelStatusInfo;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\ParcelDeliveryState;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Enum\TransportationAPI;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;


#[WithMonologChannel('api.parcel.packetery')]
class PacketeryParcel implements ParcelServiceInterface
{
    private const API_URL = 'https://www.zasilkovna.cz/api/rest';

    public function __construct(
        private readonly HttpClientInterface    $httpClient,
        private readonly LoggerInterface        $logger,
        private readonly PurchasePriceFactory   $purchasePriceFactory,
        private readonly CurrencyRepository     $currencyRepository,
    ) {}

    /**
     * @throws Throwable
     */
    public function createParcel(Purchase $purchase): string
    {
        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            throw new InvalidArgumentException('No transportation set for purchase');
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
            throw new RuntimeException('Failed to create parcel: packetId not returned from API');
        } catch (Throwable $e) {
            $this->logger->error('Parcel API exception', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public function getParcelStatus(Purchase $purchase): ParcelStatusInfo
    {
        // FIXME: Implement the actual logic to retrieve the parcel status
        return new ParcelStatusInfo(
            ParcelDeliveryState::DELIVERED,
        );
    }

    private function prepareParcelData(Purchase $purchase): array
    {
        $client = $purchase->getClient();

        $czk = $this->currencyRepository->findOneBy(['isDefault' => true]);

        $purchasePriceCalculator = $this->purchasePriceFactory->create($purchase, $czk);

        // TODO: check that calculation types for value and cod correct

        //! max 10,000 czk/400 eur
        // calculate parcel value
        $value = (clone $purchasePriceCalculator)
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
            ->getPrice();


        // calculate cash on delivery (null if cash on delivery payment not wanted)
        // TODO: is vat-exempted
        //* this is automatically converted to the countrys currency by Packeta, and will be payed to sender in CZK
        $cod = $purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY
            ? $purchasePriceCalculator
                ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                ->setVoucherCalculationType(VoucherCalculationType::WithVoucher)
                ->getPrice()
            : null;


        //? maybe country should be an enum or something like that to not make this check value match dependant
        // TODO: make cod and value calculation in the currency as well
        $country = $purchase->getPurchaseAddress()->getCountry();
        switch ($country) {
            case 'cz':
                $currency = 'CZK';
                break;

            case 'sk':
                $currency = 'EUR';
                
            default:
                $currency = 'CZK';
                break;
        }

        $currency = 'CZK'; // should be removed after cod and value calculation is done

        //! hardcoded 2Kg
        $weight = 2;
        // see "2 Parameters of the Shipment" on https://www.packeta.com/general-terms-conditions        
        
        // docs: https://docs.packeta.com/docs/api-reference/data-structures#packetattributes
        return  [
            'apiPassword' => $purchase->getTransportation()->getSecretKey(),
            'packetAttributes' => [
                'number' => (string)$purchase->getId(),
                'name' => $client->getName(),
                'surname' => $client->getSurname(),
                'email' => $client->getMail(),
                'phone' => $client->getPhone(),
                'addressId' => $purchase->getTransportation()->getToken(),
                'value' => $value, 
                'eshop' => 'yogashop',
                ...($cod !== null ? ['cod' => $cod] : []),
                'currency' => $currency,
                'weight' => $weight, 
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