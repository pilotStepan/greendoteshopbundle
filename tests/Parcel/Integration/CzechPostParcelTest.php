<?php

namespace Greendot\EshopBundle\Tests\Parcel\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Parcel\Integration\CzechPostParcel;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;

class CzechPostParcelTest extends TestCase
{
    private function makeTransportation(string $secretKeyBase64, ?string $token = 'api-token-123'): Transportation
    {
        $t = $this->createMock(Transportation::class);
        $t->method('getSecretKey')->willReturn($secretKeyBase64);
        $t->method('getToken')->willReturn($token);
        return $t;
    }

    private function makeAddress(): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getCountry')->willReturn('cz');
        $a->method('getStreet')->willReturn('Testovací 123');
        $a->method('getCity')->willReturn('Praha');
        $a->method('getZip')->willReturn('10000');
        $a->method('getCompany')->willReturn(null);
        return $a;
    }

    private function makePaymentType(bool $isCod): PaymentType
    {
        $p = $this->createMock(PaymentType::class);
        $p->method('getActionGroup')->willReturn(
            $isCod ? PaymentTypeActionGroup::ON_DELIVERY : PaymentTypeActionGroup::CARD_PAYMENT
        );
        return $p;
    }

    private function makePurchase(
        Transportation $transportation,
        ?Branch $branch,
        bool $isCod = false,
        string $transportNumber = 'BA1234567890A',
    ): Purchase {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(42);
        $client->method('getName')->willReturn('John');
        $client->method('getSurname')->willReturn('Doe');
        $client->method('getMail')->willReturn('john@example.com');
        $client->method('getPhone')->willReturn('+420777123456');

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(123);
        $purchase->method('getDateIssue')->willReturn(new DateTimeImmutable('2024-01-15'));
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getBranch')->willReturn($branch);
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPurchaseAddress')->willReturn($this->makeAddress());
        $purchase->method('getPaymentType')->willReturn($this->makePaymentType($isCod));
        $purchase->method('getTransportNumber')->willReturn($transportNumber);
        return $purchase;
    }

    private function makePriceFactory(float $price = 500.0): PurchasePriceFactory
    {
        $calculator = $this->createMock(PurchasePrice::class);
        $calculator->method('setVatCalculationType')->willReturnSelf();
        $calculator->method('setDiscountCalculationType')->willReturnSelf();
        $calculator->method('setVoucherCalculationType')->willReturnSelf();
        $calculator->method('getPrice')->willReturn($price);

        $factory = $this->createMock(PurchasePriceFactory::class);
        $factory->method('create')->willReturn($calculator);
        return $factory;
    }

    private function makeCurrencyRepo(): CurrencyRepository
    {
        $currency = $this->createMock(Currency::class);
        $repo = $this->createMock(CurrencyRepository::class);
        $repo->method('findOneBy')->willReturn($currency);
        return $repo;
    }

    private function makeService(MockHttpClient $httpClient, bool $enabled = true, string $environment = 'test'): CzechPostParcel
    {
        return new CzechPostParcel(
            $httpClient,
            new NullLogger(),
            $this->makePriceFactory(),
            $this->makeCurrencyRepo(),
            $environment,
            $enabled,
        );
    }

    /**
     * Returns 214.0 when called without services included (the buggy, VAT-exclusive,
     * shipping-exclusive price) and 353.94 when called with services included (the
     * correct, VAT-inclusive price + shipping the courier should actually collect).
     */
    private function makeCodAwarePriceFactory(): PurchasePriceFactory
    {
        $calculator = $this->createMock(PurchasePrice::class);
        $calculator->method('setVatCalculationType')->willReturnSelf();
        $calculator->method('setDiscountCalculationType')->willReturnSelf();
        $calculator->method('setVoucherCalculationType')->willReturnSelf();
        $calculator->method('getPrice')->willReturnCallback(
            static fn(bool $includeServices = false): float => $includeServices ? 353.94 : 214.0
        );

        $factory = $this->createMock(PurchasePriceFactory::class);
        $factory->method('create')->willReturn($calculator);
        return $factory;
    }

    private function makeServiceWithCodAwarePricing(MockHttpClient $httpClient, bool $enabled = true, string $environment = 'test'): CzechPostParcel
    {
        return new CzechPostParcel(
            $httpClient,
            new NullLogger(),
            $this->makeCodAwarePriceFactory(),
            $this->makeCurrencyRepo(),
            $environment,
            $enabled,
        );
    }

    private static function successResponse(string $parcelCode = 'BA1234567890A'): string
    {
        return json_encode([
            'responseHeader' => [
                'resultParcelData' => [
                    ['recordNumber' => '1', 'parcelCode' => $parcelCode, 'parcelStateResponse' => []],
                ],
            ],
        ]);
    }

    private static function statusResponse(string $statusId, string $reasonId = '1', string $date = '2024-01-16T10:00:00+01:00'): string
    {
        return json_encode([
            'idParcel' => 'BA1234567890A',
            'parcelStatus' => [
                'statusID' => $statusId,
                'reasonID' => $reasonId,
                'date' => $date,
                'statusDescription' => 'desc',
            ],
        ]);
    }

    public function testCreateParcel_handDelivery_returnsParcelCode(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::successResponse('BA1234567890A')));

        $result = $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $this->assertSame('BA1234567890A', $result);
    }

    public function testCreateParcel_usesSandboxUrlInTestEnvironment(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'test')->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $this->assertStringStartsWith('https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1/', $capturedUrl);
    }

    public function testCreateParcel_usesProdUrlInProdEnvironment(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'prod')->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $this->assertStringStartsWith('https://b2b.postaonline.cz:444/restservices/ZSKService/v1/', $capturedUrl);
    }

    public function testCreateParcel_handDelivery_usesPrefixDrAndCustomerAddress(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParams = $decoded['parcelServiceData']['parcelParams'];
        $address = $decoded['parcelServiceData']['parcelAddress']['address'];

        $this->assertSame('DR', $parcelParams['prefixParcelCode']);
        $this->assertSame('Testovací 123', $address['street']);
        $this->assertSame('Praha', $address['city']);
        $this->assertSame('10000', $address['zipCode']);
    }

    public function testCreateParcel_handDelivery_normalizesLowercaseCountryToUppercaseIso(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        // makeAddress() mocks getCountry() returning lowercase 'cz'.
        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $decoded = json_decode($capturedBody, true);
        $address = $decoded['parcelServiceData']['parcelAddress']['address'];

        $this->assertSame('CZ', $address['isoCountry']);
    }

    public function testCreateParcel_balikovna_usesPrefixNbAndBranchAddress(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('czechpost_10000');
        $branch->method('getStreet')->willReturn('Balíkovna ulice 5');
        $branch->method('getCity')->willReturn('Brno');
        $branch->method('getZip')->willReturn('60200');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $branch)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParams = $decoded['parcelServiceData']['parcelParams'];
        $address = $decoded['parcelServiceData']['parcelAddress']['address'];

        $this->assertSame('NB', $parcelParams['prefixParcelCode']);
        $this->assertSame('Balíkovna ulice 5', $address['street']);
        $this->assertSame('Brno', $address['city']);
        $this->assertSame('60200', $address['zipCode']);
    }

    public function testCreateParcel_codOrder_includesCodAmountAndServiceCode(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null, isCod: true)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParams = $decoded['parcelServiceData']['parcelParams'];

        $this->assertEquals(500.0, $parcelParams['amount']);
        $this->assertSame('123', $parcelParams['vsVoucher']);
        $this->assertContains('41', $decoded['parcelServiceData']['parcelServices']);
    }

    public function testCreateParcel_codOrder_sendsCodAmountIncludingVatAndShipping(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeServiceWithCodAwarePricing($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null, isCod: true)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParams = $decoded['parcelServiceData']['parcelParams'];

        $this->assertEquals(353.94, $parcelParams['amount']);
        // Declared/insured value must stay VAT-and-shipping-exclusive (goods value only).
        $this->assertEquals(214.0, $parcelParams['insuredValue']);
    }

    public function testCreateParcel_nonCodOrder_excludesCodAmountAndServiceCode(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null, isCod: false)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParams = $decoded['parcelServiceData']['parcelParams'];

        $this->assertSame(0, $parcelParams['amount']);
        $this->assertSame('', $parcelParams['vsVoucher']);
        $this->assertNotContains('41', $decoded['parcelServiceData']['parcelServices']);
    }

    public function testCreateParcel_sendsApiTokenAndHmacAuthorizationHeaders(): void
    {
        $capturedHeaders = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedHeaders) {
            $capturedHeaders = $options['headers'] ?? $options['normalized_headers'] ?? [];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $flat = is_array($capturedHeaders) ? implode("\n", array_map(
            fn($k, $v) => is_array($v) ? "$k: " . implode(',', $v) : "$k: $v",
            array_keys($capturedHeaders),
            $capturedHeaders
        )) : (string)$capturedHeaders;

        $this->assertStringContainsString('api-token-123', $flat);
        $this->assertStringContainsString('CP-HMAC-SHA256', $flat);
    }

    public function testCreateParcel_signatureMatchesSpecFormula(): void
    {
        $secretKeyPlain = 'my-secret-key';
        $secretKeyBase64 = base64_encode($secretKeyPlain);

        $capturedHeaders = null;
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedHeaders, &$capturedBody) {
            $capturedHeaders = $options['headers'];
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation($secretKeyBase64), null)
        );

        $headerMap = [];
        foreach ($capturedHeaders as $h) {
            [$name, $value] = array_map('trim', explode(':', $h, 2));
            $headerMap[$name] = $value;
        }

        $timestamp = $headerMap['Authorization-Timestamp'];
        $contentSha256 = $headerMap['Authorization-Content-SHA256'];
        $this->assertSame(hash('sha256', $capturedBody), $contentSha256);

        preg_match('/nonce="([^"]+)" signature="([^"]+)"/', $headerMap['Authorization'], $m);
        [, $nonce, $signature] = $m;

        $expected = base64_encode(hash_hmac('sha256', "$contentSha256;$timestamp;$nonce", $secretKeyPlain, true));
        $this->assertSame($expected, $signature);
    }

    public function testCreateParcel_missingParcelCode_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['responseHeader' => ['resultParcelData' => []]])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );
    }

    public function testCreateParcel_apiErrorResponse_throwsRuntimeExceptionWithRawResponseBody(): void
    {
        $rawErrorBody = json_encode([[
            'code' => -5,
            'status' => '401',
            'message' => 'Not found any record for provided Api-Token;',
            'date' => '22-06-2026 11:54:41',
            'x-request-id' => '62688e30bbcc1e55',
        ]]);
        $httpClient = new MockHttpClient(new MockResponse($rawErrorBody, ['http_code' => 401]));

        try {
            $this->makeService($httpClient)->createParcel(
                $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Not found any record for provided Api-Token', $e->getMessage());
            $this->assertStringContainsString('401', $e->getMessage());
        }
    }

    public function testGetParcelStatus_apiErrorResponse_throwsRuntimeExceptionWithRawResponseBody(): void
    {
        $rawErrorBody = json_encode([[
            'code' => -5,
            'status' => '401',
            'message' => 'Not found any record for provided Api-Token;',
            'date' => '22-06-2026 11:54:41',
            'x-request-id' => '62688e30bbcc1e55',
        ]]);
        $httpClient = new MockHttpClient(new MockResponse($rawErrorBody, ['http_code' => 401]));

        try {
            $this->makeService($httpClient)->getParcelStatus(
                $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Not found any record for provided Api-Token', $e->getMessage());
            $this->assertStringContainsString('401', $e->getMessage());
        }
    }

    public function testGetParcelStatus_sendsGetRequestToIdParcelEndpoint(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedMethod, &$capturedUrl) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            return new MockResponse(self::statusResponse('2'));
        });

        $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null, transportNumber: 'BA1234567890A')
        );

        $this->assertSame('GET', $capturedMethod);
        $this->assertStringEndsWith('/parcelStatuses/current/idParcel/BA1234567890A', $capturedUrl);
    }

    public function testGetParcelStatus_statusId2_returnsInTransit(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse('2')));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::IN_TRANSIT, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusId4_returnsDelivered(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse('4')));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::DELIVERED, $result->state);
        $this->assertTrue($result->state->isFinal());
    }

    public function testGetParcelStatus_unmappedCode_fallsBackToReceivedData(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse('999')));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testSupports_whenEnabled_returnsTrueForBothCzechPostServices(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertTrue($service->supports(TransportationAPI::CP_DO_RUKY));
        $this->assertTrue($service->supports(TransportationAPI::CP_BALIKOVNA));
    }

    public function testSupports_whenDisabled_returnsFalse(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: false);

        $this->assertFalse($service->supports(TransportationAPI::CP_DO_RUKY));
        $this->assertFalse($service->supports(TransportationAPI::CP_BALIKOVNA));
    }

    public function testSupports_neverMatchesOtherCarrier(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertFalse($service->supports(TransportationAPI::DPD));
        $this->assertFalse($service->supports(TransportationAPI::PACKETA));
    }
}
