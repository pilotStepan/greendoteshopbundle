<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

/**
 * Packeta home/address delivery via an external carrier (addressId = carrier ID, not a branch ID).
 * The carrier ID, and whether it requires the courier-number/label follow-up calls, is project-specific
 * configuration (Transportation.token) — this class must stay carrier-agnostic.
 */
#[WithMonologChannel('api.parcel.packetery')]
class PacketeryAddressParcel implements ParcelServiceInterface
{
    use PacketeryApiTrait;

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

        $xml = $this->callPacketeryApi('createPacket', $this->prepareParcelData($purchase), 'createPacket', $purchase);
        $packetId = (string)$xml->result->id;

        $this->fetchCourierTrackingAndLabel($purchase, $transportation, $packetId);

        return (string)$xml->result->barcode;
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

        ['value' => $value, 'cod' => $cod] = $this->resolvePriceAndCod($purchase, $currency);

        $packetAttributes = [
            'number' => (string)$purchase->getId(),
            'name' => $address->getShipName() ?? $client->getName(),
            'surname' => $address->getShipSurname() ?? $client->getSurname(),
            'email' => $client->getMail(),
            'phone' => $client->getPhone(),
            'addressId' => (int)$transportation->getToken(),
            'street' => $address->getShipStreet() ?? $address->getStreet(),
            'city' => $address->getShipCity() ?? $address->getCity(),
            'zip' => $address->getShipZip() ?? $address->getZip(),
            'value' => $value,
            'currency' => $currency,
            'weight' => 1,
            'eshop_id' => $this->eshopName,
        ];

        if ($cod !== null) {
            $packetAttributes['cod'] = $cod;
        }

        return [
            'apiPassword' => $transportation->getSecretKey(),
            'packetAttributes' => $packetAttributes,
        ];
    }

    /**
     * External-carrier delivery (addressId is a carrier, not a Packeta branch) requires obtaining
     * the carrier's own tracking number and shipping label after the packet is created in Packeta's
     * system, otherwise the parcel must be relabeled in depot, delaying delivery.
     */
    private function fetchCourierTrackingAndLabel(Purchase $purchase, Transportation $transportation, string $packetId): void
    {
        $apiPassword = $transportation->getSecretKey();

        $numberXml = $this->callPacketeryApi('packetCourierNumber', [
            'apiPassword' => $apiPassword,
            'packetId' => $packetId,
        ], 'packetCourierNumber', $purchase);

        $courierNumber = (string)$numberXml->result->courierNumber;
        $purchase->setCourierNumber($courierNumber);

        $labelXml = $this->callPacketeryApi('packetCourierLabelPdf', [
            'apiPassword' => $apiPassword,
            'packetId' => $packetId,
            'courierNumber' => $courierNumber,
        ], 'packetCourierLabelPdf', $purchase);

        $this->logger->info('Fetched Packeta courier tracking number for address delivery', [
            'purchaseId' => $purchase->getId(),
            'packetId' => $packetId,
            'courierNumber' => $courierNumber,
            'labelReceived' => isset($labelXml->result) && (string)$labelXml->result !== '',
        ]);
    }

    public function supports(TransportationAPI $transportationAPI): bool
    {
        return $this->enabled && $transportationAPI === TransportationAPI::PACKETA_ADDRESS;
    }
}
