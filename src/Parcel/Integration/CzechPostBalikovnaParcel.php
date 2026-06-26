<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Throwable;
use Psr\Log\LoggerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Parcel\Exception\PermanentParcelException;
use Greendot\EshopBundle\Parcel\Exception\TransientParcelException;
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
 * Handles CP_BALIKOVNA (pickup point, prefixParcelCode "NB") through the same /parcelService endpoint
 * used by CzechPostParcel for CP_DO_RUKY.
 */
#[WithMonologChannel('api.parcel.czech_post')]
class CzechPostBalikovnaParcel implements ParcelServiceInterface
{
    use CzechPostHmacAuthTrait;

    private const PROD_URL = 'https://b2b.postaonline.cz:444/restservices/ZSKService/v1';
    private const SANDBOX_URL = 'https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1';
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface  $httpClient,
        private readonly LoggerInterface      $logger,
        private readonly PurchasePriceFactory $purchasePriceFactory,
        private readonly CurrencyRepository   $currencyRepository,
        #[Autowire(param: 'greendot_eshop.parcel.czech_post.customer_id')]
        private readonly string               $customerId,
        #[Autowire(param: 'greendot_eshop.parcel.czech_post.post_code')]
        private readonly string               $postCode,
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
            throw new PermanentParcelException('No transportation set for purchase');
        }

        $branch = $purchase->getBranch();
        if (!$branch instanceof Branch) {
            $this->logger->error('No branch (pickup point) set for purchase', ['purchaseId' => $purchase->getId()]);
            throw new PermanentParcelException('No branch set for purchase');
        }

        $body = $this->prepareParcelData($purchase, $branch);
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/parcelService', [
                'headers' => $this->getHeaders($transportation, $jsonBody),
                'body' => $jsonBody,
                ...$this->getTlsOptions(),
            ]);

            $data = $response->toArray(false);

            $parcelCode = $data['responseHeader']['resultParcelData'][0]['parcelCode'] ?? null;
            if (!$parcelCode) {
                $rawResponse = $response->getContent(false);
                $this->logger->error('Failed to create Balikovna parcel', [
                    'purchaseId' => $purchase->getId(),
                    'httpStatusCode' => $response->getStatusCode(),
                    'response' => $rawResponse,
                ]);
                throw new TransientParcelException("Failed to create Balikovna parcel: $rawResponse");
            }

            return (string)$parcelCode;
        } catch (PermanentParcelException | TransientParcelException $e) {
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
            throw new PermanentParcelException('No transport number for purchase');
        }

        $transportation = $purchase->getTransportation();
        if (!$transportation instanceof Transportation) {
            $this->logger->error('No transportation set for purchase', ['purchaseId' => $purchase->getId()]);
            throw new PermanentParcelException('No transportation set for purchase');
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
                throw new TransientParcelException("Czech Post getParcelStatus failed: $rawResponse");
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
        } catch (PermanentParcelException | TransientParcelException $e) {
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
        return $this->enabled && $transportationAPI === TransportationAPI::CP_BALIKOVNA;
    }

    private function prepareParcelData(Purchase $purchase, Branch $branch): array
    {
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

        $weight = 1;

        $parcelParams = [
            'recordID' => (string)$purchase->getId(),
            'prefixParcelCode' => 'NB',
            'weight' => number_format($weight, 2),
            'insuredValue' => $insuredValue,
            'amount' => $codAmount,
            'currency' => 'CZK',
            'vsParcel' => (string)$purchase->getId(),
            'length' => 0,
            'width' => 0,
            'height' => 0,
        ];

        if ($isCod) {
            $parcelParams['vsVoucher'] = (string)$purchase->getId();
        }

        return [
            'parcelServiceHeader' => [
                'parcelServiceHeaderCom' => [
                    'transmissionDate' => $purchase->getDateIssue()->format('Y-m-d'),
                    'customerID' => $this->customerId,
                    'postCode' => $this->postCode,
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
                'parcelParams' => $parcelParams,
                'parcelAddress' => $this->buildParcelAddress($purchase, $branch),
                // (https://www.postaonline.cz/podanionline/ePOST-dokumentace/231standardniObsah.html):
                // '7' = Udaná cena (declared/insured value) - always sent since insuredValue is always populated;
                // '41' = Bezdokladová dobírka (document-less cash on delivery) - sent only for ON_DELIVERY orders.
                'parcelServices' => $isCod ? ['7', '41'] : ['7'],
            ],
        ];
    }

    private function buildParcelAddress(Purchase $purchase, Branch $branch): array
    {
        $client = $purchase->getClient();
        $purchaseAddress = $purchase->getPurchaseAddress();

        return [
            'recordID' => (string)$client->getId(),
            'firstName' => $purchaseAddress->getShipName() ?? $client->getName(),
            'surname' => $purchaseAddress->getShipSurname() ?? $client->getSurname(),
            'company' => $purchaseAddress->getShipCompany() ?? $purchaseAddress->getCompany() ?? '',
            'aditionAddress' => '',
            'subject' => 'F',
            // NB (pickup point) parcels carry only a fixed 'Balíkovna'
            // street and the pickup point's own ID as 'zipCode'
            'address' => [
                'street' => 'Balíkovna',
                'zipCode' => str_replace('czechpost_', '', (string)$branch->getProviderId()),
            ],
            'mobilNumber' => $client->getPhone(),
            'phoneNumber' => '',
            'emailAddress' => $client->getMail(),
        ];
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
