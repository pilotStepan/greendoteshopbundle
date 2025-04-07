<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CzechPostParcel implements ParcelServiceInterface
{
    private const API_BASE_URL = 'https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1/*';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger
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
            $response = $this->httpClient->request('POST', self::API_BASE_URL . '/parcelService', [
                'headers' => $this->getHeaders($transportation),
                'json' => $requestData,
            ]);

            $data = $response->toArray();

            if (isset($data['resultParcelData']['parcelCode'])) {
                return $data['resultParcelData']['parcelCode'];
            }

            $this->logger->error('Failed to create parcel', [
                'purchaseId' => $purchase->getId(),
                'response' => $data,
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Exception when creating parcel', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getParcelStatus(Purchase $purchase): ?array
    {
        $transportNumber = $purchase->getTransportNumber();
        if (!$transportNumber) {
            $this->logger->error('No transport number for purchase', ['purchaseId' => $purchase->getId()]);
            return null;
        }

        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL . '/parcelStatus', [
                'headers' => $this->getHeaders($transportation),
                'query' => ['parcelIds' => $transportNumber],
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Exception when fetching parcel status', [
                'purchaseId' => $purchase->getId(),
                'transportNumber' => $transportNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function prepareParcelData(Purchase $purchase): array
    {
        $client = $purchase->getClient();
        $totalWeight = 20;

        return [
            'parcelServiceHeader' => [
                'parcelServiceHeaderCom' => [
                    'transmissionDate' => $purchase->getDateIssue()->format('Y-m-d'),
                    'customerID' => $client->getId(),
                    'postCode' => $client->getZip(),
                    'locationNumber' => 1,
                ],
                'printParams' => [
                    'idForm' => 101,
                    'shiftHorizontal' => 0,
                    'shiftVertical' => 0,
                ],
                'position' => 1,
            ],
            'parcelServiceData' => [
                'parcelParams' => [
                    'recordID' => (string)$purchase->getId(),
                    'prefixParcelCode' => 'DR',
                    'weight' => number_format($totalWeight, 2),
                    'insuredValue' => 0,
                    'amount' => $purchase->getTotalPrice(),
                    'currency' => 'CZK',
                    'vsVoucher' => '',
                    'vsParcel' => '',
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                ],
                'parcelAddress' => [
                    'recordID' => (string)$client->getId(),
                    'firstName' => $client->getName(),
                    'surname' => $client->getSurname(),
                    'company' => $client->getCompany() ?? '',
                    'aditionAddress' => '',
                    'subject' => 'F',
                    'address' => [
                        'street' => $client->getStreet(),
                        'houseNumber' => 1,
                        'sequenceNumber' => '',
                        'cityPart' => '',
                        'city' => $client->getCity(),
                        'zipCode' => $client->getZip(),
                        'isoCountry' => $client->getCountry(),
                    ],
                    'mobilNumber' => $client->getPhone(),
                    'phoneNumber' => $client->getPhone() ?? '',
                    'emailAddress' => $client->getMail(),
                ],
                'parcelServices' => '',
            ],
        ];
    }


    private function getHeaders(Transportation $transportation): array
    {
        $timestamp = time();
        $nonce     = $this->generateNonce();

        return [
            'Api-Token'                    => $transportation->getToken(),
            'Authorization-Timestamp'      => $timestamp,
            'Authorization-Content-SHA256' => hash('sha256', ''),
            'Authorization'                => $this->generateHmacAuth($transportation, $timestamp, $nonce),
            'Content-Type'                 => 'application/json;charset=UTF-8',
        ];
    }

    private function generateNonce(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function generateHmacAuth(Transportation $transportation, int $timestamp, string $nonce): string
    {
        $signature       = hash_hmac('sha256', "Authorization-Timestamp;$nonce", $transportation->getSecretKey(), true);
        $signatureBase64 = base64_encode($signature);

        return "CP-HMAC-SHA256 nonce=\"$nonce\" signature=\"$signatureBase64\"";
    }
}
