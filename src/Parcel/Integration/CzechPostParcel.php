<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Throwable;
use RuntimeException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Entity\Project\Branch;
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
 * Integrates Czech Post's B2B-ZSKService REST API (https://www.postaonline.cz/dokumentaceapi/b2b/api/B2B3-ZSKService/B2B-ZSKService-1.5.0.yaml).
 * Handles both CP_DO_RUKY (hand delivery, prefixParcelCode "DR") and CP_BALIKOVNA (pickup point, prefixParcelCode "NB") in one service.
 */
#[WithMonologChannel('api.parcel.czech_post')]
class CzechPostParcel implements ParcelServiceInterface
{
    private const PROD_URL = 'https://b2b.postaonline.cz:444/restservices/ZSKService/v1';
    private const SANDBOX_URL = 'https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1';
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface  $httpClient,
        private readonly LoggerInterface      $logger,
        private readonly PurchasePriceFactory $purchasePriceFactory,
        private readonly CurrencyRepository   $currencyRepository,
        #[Autowire(param: 'kernel.environment')]
        string                                $environment,
        #[Autowire(param: 'greendot_eshop.parcel.czech_post.enabled')]
        private readonly bool                 $enabled = false,
    )
    {
        $this->baseUrl = in_array($environment, ['test', 'dev'], true) ? self::SANDBOX_URL : self::PROD_URL;
    }

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

        $body = $this->prepareParcelData($purchase);
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/parcelService', [
                'headers' => $this->getHeaders($transportation, $jsonBody),
                'body' => $jsonBody,
                ...$this->getTlsOptions(),
            ]);

            $data = $response->toArray(false);

            $parcelCode = $data['responseHeader']['resultParcelData'][0]['parcelCode'] ?? null;
            if ($parcelCode === null) {
                $rawResponse = $response->getContent(false);
                $this->logger->error('Failed to create parcel', [
                    'purchaseId' => $purchase->getId(),
                    'httpStatusCode' => $response->getStatusCode(),
                    'response' => $rawResponse,
                ]);
                throw new RuntimeException("Failed to create parcel: $rawResponse");
            }

            return (string)$parcelCode;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Czech Post HTTP exception on createParcel', [
                'purchaseId' => $purchase->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @throws Throwable
     */
    public function getParcelStatus(Purchase $purchase): ParcelStatusInfoDto
    {
        $transportNumber = $purchase->getTransportNumber();
        if (!$transportNumber) {
            $this->logger->error('No transport number for purchase', ['purchaseId' => $purchase->getId()]);
            throw new InvalidArgumentException('No transport number for purchase');
        }

        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            throw new InvalidArgumentException('No transportation set for purchase');
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $this->baseUrl . '/parcelStatuses/current/idParcel/' . $transportNumber,
                ['headers' => $this->getHeaders($transportation, null), ...$this->getTlsOptions()],
            );

            $data = $response->toArray(false);
            $status = $data['parcelStatus'] ?? null;
            if ($status === null) {
                $rawResponse = $response->getContent(false);
                $this->logger->error('Failed to get parcel status', [
                    'purchaseId' => $purchase->getId(),
                    'transportNumber' => $transportNumber,
                    'httpStatusCode' => $response->getStatusCode(),
                    'response' => $rawResponse,
                ]);
                throw new RuntimeException("Czech Post getParcelStatus failed: $rawResponse");
            }

            $statusId = (string)($status['statusID'] ?? '');
            $reasonId = (string)($status['reasonID'] ?? '');
            $occurredAt = isset($status['date']) ? new \DateTimeImmutable((string)$status['date']) : null;

            return new ParcelStatusInfoDto(
                state: $this->mapStatusCode($statusId, $reasonId),
                details: [
                    'statusID' => $statusId,
                    'reasonID' => $reasonId,
                    'statusDescription' => $status['statusDescription'] ?? null,
                ],
                occurredAt: $occurredAt,
            );
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Czech Post HTTP exception on getParcelStatus', [
                'purchaseId' => $purchase->getId(),
                'transportNumber' => $transportNumber,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function supports(TransportationAPI $transportationAPI): bool
    {
        return $this->enabled && in_array($transportationAPI, [TransportationAPI::CP_DO_RUKY, TransportationAPI::CP_BALIKOVNA], true);
    }

    private function prepareParcelData(Purchase $purchase): array
    {
        $client = $purchase->getClient();
        $branch = $purchase->getBranch();

        $priceCalculator = $this->purchasePriceFactory->create($purchase, $this->currencyRepository->findOneBy(['isDefault' => true]));

        $insuredValue = (clone $priceCalculator)
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->setVoucherCalculationType(VoucherCalculationType::WithoutVoucher)
            ->getPrice()
        ;

        $isCod = $purchase->getPaymentType()->getActionGroup() === PaymentTypeActionGroup::ON_DELIVERY;
        $codAmount = $isCod
            ? (clone $priceCalculator)
                ->setVatCalculationType(VatCalculationType::WithVAT)
                ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
                ->setVoucherCalculationType(VoucherCalculationType::WithVoucher)
                ->getPrice(true)
            : 0;

        // TODO: derive weight from order items (no weight field on ProductVariant yet)
        $weight = 2;

        return [
            'parcelServiceHeader' => [
                'parcelServiceHeaderCom' => [
                    'transmissionDate' => $purchase->getDateIssue()->format('Y-m-d'),
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
                    'prefixParcelCode' => $branch !== null ? 'NB' : 'DR',
                    'weight' => number_format($weight, 2),
                    'insuredValue' => $insuredValue,
                    'amount' => $codAmount,
                    'currency' => 'CZK',
                    'vsVoucher' => $isCod ? (string)$purchase->getId() : '',
                    'vsParcel' => (string)$purchase->getId(),
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                ],
                'parcelAddress' => $this->buildParcelAddress($purchase, $branch),
                // (https://www.postaonline.cz/podanionline/ePOST-dokumentace/231standardniObsah.html):
                // '7' = Udaná cena (declared/insured value) - always sent since insuredValue is always populated;
                // '41' = Bezdokladová dobírka (document-less cash on delivery) - sent only for ON_DELIVERY orders.
                'parcelServices' => $isCod ? ['7', '41'] : ['7'],
            ],
        ];
    }

    private function buildParcelAddress(Purchase $purchase, ?Branch $branch): array
    {
        $client = $purchase->getClient();

        // Intentional: Czech Post's AddressCOMMON/ParcelAddress schema has no pickup-point/location-ID field (unlike Packeta's addressId) — pickup points are addressed by street/city/zip only.
        $address = $branch !== null
            ? [
                'street' => $branch->getStreet(),
                'houseNumber' => '',
                'sequenceNumber' => '',
                'cityPart' => '',
                'city' => $branch->getCity(),
                'zipCode' => $branch->getZip(),
                'isoCountry' => 'CZ',
            ]
            : [
                'street' => $purchase->getPurchaseAddress()->getStreet(),
                'houseNumber' => '',
                'sequenceNumber' => '',
                'cityPart' => '',
                'city' => $purchase->getPurchaseAddress()->getCity(),
                'zipCode' => $purchase->getPurchaseAddress()->getZip(),
                'isoCountry' => $this->normalizeIsoCountry($purchase->getPurchaseAddress()->getCountry()),
            ];

        return [
            'recordID' => (string)$client->getId(),
            'firstName' => $client->getName(),
            'surname' => $client->getSurname(),
            'company' => $purchase->getPurchaseAddress()->getCompany() ?? '',
            'aditionAddress' => '',
            'subject' => 'F',
            'address' => $address,
            'mobilNumber' => $client->getPhone(),
            'phoneNumber' => '',
            'emailAddress' => $client->getMail(),
        ];
    }

    /**
     * Czech Post's chain is signed by their own PostSignum Public CA 5 root, which is absent from the
     * standard system CA bundle (curl reports "self signed certificate in certificate chain"). Verification
     * is disabled here rather than for the whole app's HttpClient, scoping the risk to this integration.
     */
    private function getTlsOptions(): array
    {
        return [
            'verify_peer' => false,
            'verify_host' => false,
        ];
    }

    private function getHeaders(Transportation $transportation, ?string $body): array
    {
        $timestamp = time();
        $nonce = $this->generateNonce();

        $headers = [
            'Api-Token' => $transportation->getToken(),
            'Authorization-Timestamp' => $timestamp,
            'Authorization' => $this->generateHmacAuth($transportation, $timestamp, $nonce, $body),
            'Content-Type' => 'application/json;charset=UTF-8',
        ];

        if ($body !== null) {
            $headers['Authorization-Content-SHA256'] = hash('sha256', $body);
        }

        return $headers;
    }

    private function generateNonce(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        );
    }

    private function generateHmacAuth(Transportation $transportation, int $timestamp, string $nonce, ?string $body): string
    {
        $contentSha256 = $body !== null ? hash('sha256', $body) : '';
        $stringToSign = "$contentSha256;$timestamp;$nonce";

        $secretKey = base64_decode((string)$transportation->getSecretKey(), true) ?: (string)$transportation->getSecretKey();
        $signature = hash_hmac('sha256', $stringToSign, $secretKey, true);
        $signatureBase64 = base64_encode($signature);

        return "CP-HMAC-SHA256 nonce=\"$nonce\",signature=\"$signatureBase64\"";
    }

    /**
     * Spec requires a 2-char ISO country code defaulting to 'CZ'; PurchaseAddress::getCountry() is a free-form
     * nullable string with no format constraint, so normalize defensively.
     */
    private function normalizeIsoCountry(?string $country): string
    {
        $normalized = $country !== null ? strtoupper(trim($country)) : '';
        return $normalized !== '' ? $normalized : 'CZ';
    }

    /**
     * Czech Post's spec doesn't publish an exhaustive statusID/reasonID code table, so unmapped codes are logged
     * and treated as RECEIVED_DATA rather than crashing, matching DpdParcel/PacketeryParcel's defensive fallback.
     */
    private function mapStatusCode(string $statusId, string $reasonId): ParcelDeliveryStateEnum
    {
        $state = match ($statusId) {
            '1'     => ParcelDeliveryStateEnum::RECEIVED_DATA,
            '2'     => ParcelDeliveryStateEnum::IN_TRANSIT,
            '3'     => ParcelDeliveryStateEnum::READY_FOR_PICKUP,
            '4'     => ParcelDeliveryStateEnum::DELIVERED,
            '5'     => ParcelDeliveryStateEnum::NOT_PICKED_UP,
            '6'     => ParcelDeliveryStateEnum::CANCELLED,
            default => null,
        };

        if ($state === null) {
            $this->logger->warning('Unmapped Czech Post parcel status code', ['statusID' => $statusId, 'reasonID' => $reasonId]);
            return ParcelDeliveryStateEnum::RECEIVED_DATA;
        }

        return $state;
    }
}
