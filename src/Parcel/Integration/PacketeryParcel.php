<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Psr\Log\LoggerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Parcel\Exception\PermanentParcelException;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

#[WithMonologChannel('api.parcel.packetery')]
class PacketeryParcel implements ParcelServiceInterface
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
            throw new PermanentParcelException('No transportation set for purchase');
        }

        $xml = $this->callPacketeryApi('createPacket', $this->prepareParcelData($purchase), 'createPacket', $purchase);

        return (string)$xml->result->barcode;
    }

    private function prepareParcelData(Purchase $purchase): array
    {
        $transportation = $purchase->getTransportation();
        $client = $purchase->getClient();
        $address = $purchase->getPurchaseAddress();
        $country = $address->getShipCountry() ?? $address->getCountry();

        $branch = $purchase->getBranch();
        if ($branch === null) {
            $this->logger->error('No pickup branch set for Packeta pickup-point purchase', ['purchaseId' => $purchase->getId()]);
            throw new PermanentParcelException('No pickup branch set for purchase');
        }

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
            'addressId' => (int)str_replace('packeta_', '', $branch->getProviderId()),
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

    public function supports(TransportationAPI $transportationAPI): bool
    {
        return $this->enabled && $transportationAPI === TransportationAPI::PACKETA;
    }
}
