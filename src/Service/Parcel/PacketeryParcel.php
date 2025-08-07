<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Exception;
use SimpleXMLElement;
use Psr\Log\LoggerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;

#[WithMonologChannel('api.parcel.packetery')]
class PacketeryParcel implements ParcelServiceInterface
{
    private const API_URL = 'https://www.zasilkovna.cz/api/rest';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
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
            $this->logger->error('API exception', [
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

        return [
            'apiPassword' => $purchase->getTransportation()->getSecretKey(),
            'packetAttributes' => [
                'number' => (string)$purchase->getId(),
                'name' => $client->getName(),
                'surname' => $client->getSurname(),
                'email' => $client->getMail(),
                'phone' => $client->getPhone(),
                'addressId' => $purchase->getTransportation()->getToken(),
                'value' => $purchase->getTotalPrice(),
                'eshop' => 'yogashop',
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

    public function supports(int $transportationId): bool
    {
        return $transportationId === 3;
    }
}