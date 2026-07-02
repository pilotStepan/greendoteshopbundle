<?php

namespace Greendot\EshopBundle\Tests\Parcel\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Parcel\Integration\DpdParcel;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;

class DpdParcelTest extends TestCase
{
    private function makeTransportation(string $jwt): Transportation
    {
        $t = $this->createMock(Transportation::class);
        $t->method('getSecretKey')->willReturn($jwt);
        return $t;
    }

    private function makeAddress(string $country = 'cz'): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getCountry')->willReturn($country);
        $a->method('getShipCountry')->willReturn($country);
        $a->method('getShipName')->willReturn('John');
        $a->method('getShipSurname')->willReturn('Doe');
        $a->method('getShipCompany')->willReturn(null);
        $a->method('getShipStreet')->willReturn('Testovací 123');
        $a->method('getShipCity')->willReturn('Praha');
        $a->method('getShipZip')->willReturn('100 00');
        return $a;
    }

    /**
     * Most orders ship to the billing address, so the ship_* override fields are null
     * and the DPD API call must fall back to the base street/city/zip/company fields.
     */
    private function makeAddressWithoutShipOverride(string $country = 'cz'): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getCountry')->willReturn($country);
        $a->method('getShipCountry')->willReturn(null);
        $a->method('getShipName')->willReturn(null);
        $a->method('getShipSurname')->willReturn(null);
        $a->method('getShipCompany')->willReturn(null);
        $a->method('getShipStreet')->willReturn(null);
        $a->method('getShipCity')->willReturn(null);
        $a->method('getShipZip')->willReturn(null);
        $a->method('getCompany')->willReturn('Acme s.r.o.');
        $a->method('getStreet')->willReturn('Hlavní 1');
        $a->method('getCity')->willReturn('Brno');
        $a->method('getZip')->willReturn('602 00');
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
        bool $isCod = false,
        string $country = 'cz',
        string $transportNumber = '13955081839853',
        string $shipmentId = '52172',
        ?PurchaseAddress $address = null,
    ): Purchase {
        $client = $this->createMock(Client::class);
        $client->method('getName')->willReturn('John');
        $client->method('getSurname')->willReturn('Doe');
        $client->method('getMail')->willReturn('john@example.com');
        $client->method('getPhone')->willReturn('+420777123456');

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(123);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPurchaseAddress')->willReturn($address ?? $this->makeAddress($country));
        $purchase->method('getPaymentType')->willReturn($this->makePaymentType($isCod));
        $purchase->method('getTransportNumber')->willReturn($transportNumber);
        $purchase->method('getShipmentId')->willReturn($shipmentId);
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

    private function makeService(MockHttpClient $httpClient, string $environment = 'test', bool $enabled = true): DpdParcel
    {
        return new DpdParcel(
            $httpClient,
            new NullLogger(),
            $this->makePriceFactory(),
            $this->makeCurrencyRepo(),
            'TestCustomer',
            '56',
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

    private function makeServiceWithCodAwarePricing(MockHttpClient $httpClient, string $environment = 'test', bool $enabled = true): DpdParcel
    {
        return new DpdParcel(
            $httpClient,
            new NullLogger(),
            $this->makeCodAwarePriceFactory(),
            $this->makeCurrencyRepo(),
            'TestCustomer',
            '56',
            $environment,
            $enabled,
        );
    }

    private static function successResponse(int $shipmentId = 52172): string
    {
        return json_encode([
            'transactionId' => 4383,
            'shipmentResults' => [
                [
                    'numOrder' => 1,
                    'shipmentId' => $shipmentId,
                    'mpsId' => '13955081839853',
                    'labelFile' => 'data:application/pdf;base64,',
                ],
            ],
        ]);
    }

    private static function statusResponse(int $shipmentId = 52172, int $status = 2): string
    {
        return json_encode([
            'transactionId' => 4399,
            'shipment' => [
                'shipmentId' => $shipmentId,
                'status' => $status,
            ],
        ]);
    }

    public function testCreateParcel_returnsMpsIdAsCustomerFacingTrackingNumber(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::successResponse(52172)));

        $result = $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame('13955081839853', $result);
    }

    public function testCreateParcel_storesShipmentIdViaAdditionalInfo(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::successResponse(52172)));

        $purchase = $this->makePurchase($this->makeTransportation('jwt123'));
        $purchase->expects($this->once())->method('setShipmentId')->with('52172');

        $this->makeService($httpClient)->createParcel($purchase);
    }

    public function testCreateParcel_sendsBearerAuthorizationHeader(): void
    {
        $capturedHeaders = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedHeaders) {
            $capturedHeaders = $options['headers'] ?? [];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt-secret'))
        );

        $this->assertContains('Authorization: Bearer jwt-secret', $capturedHeaders);
    }

    public function testCreateParcel_sendsCustomerIdAndSenderAddressId(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $decoded = json_decode($capturedBody, true);
        $this->assertArrayNotHasKey('buCode', $decoded);
        $this->assertSame('TestCustomer', $decoded['customerId']);
        $this->assertSame(56, $decoded['shipments'][0]['senderAddressId']);
        $this->assertSame('Praha', $decoded['shipments'][0]['receiver']['city']);
        $this->assertSame('10000', $decoded['shipments'][0]['receiver']['zipCode']);
        $this->assertSame('Testovací 123', $decoded['shipments'][0]['receiver']['street']);
        $this->assertSame('John Doe', $decoded['shipments'][0]['receiver']['contactName']);
        $this->assertSame('john@example.com', $decoded['shipments'][0]['receiver']['contactEmail']);
        $this->assertSame('+420777123456', $decoded['shipments'][0]['receiver']['contactPhone']);
        $this->assertSame('123', $decoded['shipments'][0]['reference1']);
        $this->assertSame('123', $decoded['shipments'][0]['parcels'][0]['reference1']);
        $this->assertSame(1, $decoded['shipments'][0]['parcels'][0]['weight']);
    }

    public function testCreateParcel_noShipOverride_fallsBackToBaseAddressFields(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'), address: $this->makeAddressWithoutShipOverride())
        );

        $decoded = json_decode($capturedBody, true);
        $this->assertSame('Hlavní 1', $decoded['shipments'][0]['receiver']['street']);
        $this->assertSame('Brno', $decoded['shipments'][0]['receiver']['city']);
        $this->assertSame('60200', $decoded['shipments'][0]['receiver']['zipCode']);
        $this->assertSame('Acme s.r.o.', $decoded['shipments'][0]['receiver']['companyName']);
    }

    public function testCreateParcel_codOrder_includesCod(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'), isCod: true)
        );

        $decoded = json_decode($capturedBody, true);
        $this->assertArrayHasKey('service', $decoded['shipments'][0]);
        $this->assertSame('101', $decoded['shipments'][0]['service']['mainServiceCode']);
        $this->assertSame('CZK', $decoded['shipments'][0]['service']['additionalService']['cod']['currency']);
        $this->assertSame('123', $decoded['shipments'][0]['service']['additionalService']['cod']['reference']);
        $this->assertSame('Even', $decoded['shipments'][0]['service']['additionalService']['cod']['split']);
    }

    public function testCreateParcel_codOrder_sendsCodAmountIncludingVatAndShipping(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeServiceWithCodAwarePricing($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'), isCod: true)
        );

        $decoded = json_decode($capturedBody, true);

        $this->assertEquals('353.94', $decoded['shipments'][0]['service']['additionalService']['cod']['amount']);
    }

    public function testCreateParcel_nonCodOrder_sendsMainServiceCodeWithoutAdditionalService(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'), isCod: false)
        );

        $decoded = json_decode($capturedBody, true);
        $this->assertSame('101', $decoded['shipments'][0]['service']['mainServiceCode']);
        $this->assertArrayNotHasKey('additionalService', $decoded['shipments'][0]['service']);
    }

    public function testCreateParcel_slovakDestination_usesEurCurrencyAndLooksUpNonDefaultCurrency(): void
    {
        // Only 'cz' (-> CZK, isDefault: true) is exercised elsewhere; 'sk' must select EUR
        // and query the *non*-default currency, not just "some currency".
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $currency = $this->createMock(Currency::class);
        $currencyRepo = $this->createMock(CurrencyRepository::class);
        $currencyRepo->expects($this->once())->method('findOneBy')->with(['isDefault' => false])->willReturn($currency);

        $service = new DpdParcel(
            $httpClient,
            new NullLogger(),
            $this->makeCodAwarePriceFactory(),
            $currencyRepo,
            'TestCustomer',
            '56',
            'test',
            true,
        );

        $service->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'), isCod: true, country: 'sk')
        );

        $decoded = json_decode($capturedBody, true);
        $this->assertSame('EUR', $decoded['shipments'][0]['service']['additionalService']['cod']['currency']);
        $this->assertSame('SK', $decoded['shipments'][0]['receiver']['countryCode']);
    }

    public function testCreateParcel_defaultEnabledConstructorArgumentIsFalse(): void
    {
        $service = new DpdParcel(
            new MockHttpClient(),
            new NullLogger(),
            $this->makePriceFactory(),
            $this->makeCurrencyRepo(),
            'TestCustomer',
            '56',
            'test',
            // $enabled omitted -> must default to false
        );

        $this->assertFalse($service->supports(TransportationAPI::DPD));
    }

    public function testCreateParcel_missingShipmentId_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['transactionId' => 1, 'shipmentResults' => []])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testCreateParcel_shipmentIdPresentButMpsIdMissing_throwsRuntimeException(): void
    {
        // Isolates the `mpsId === null` half of the `||` check from `shipmentId === null`.
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'transactionId' => 1,
            'shipmentResults' => [['numOrder' => 1, 'shipmentId' => 52172]],
        ])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testCreateParcel_mpsIdPresentButShipmentIdMissing_throwsRuntimeException(): void
    {
        // Isolates the `shipmentId === null` half of the `||` check from `mpsId === null`.
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'transactionId' => 1,
            'shipmentResults' => [['numOrder' => 1, 'mpsId' => '13955081839853']],
        ])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testCreateParcel_storesShipmentIdAsStringNotInt(): void
    {
        // JSON decodes shipmentId as an int; setShipmentId((string)$shipmentId) must actually
        // cast it — a loose ->with('52172') match wouldn't catch a missing (string) cast.
        $httpClient = new MockHttpClient(new MockResponse(self::successResponse(52172)));

        $capturedArg = null;
        $purchase = $this->makePurchase($this->makeTransportation('jwt123'));
        $purchase->expects($this->once())->method('setShipmentId')
            ->with($this->callback(function ($arg) use (&$capturedArg) {
                $capturedArg = $arg;
                return true;
            }))
        ;

        $this->makeService($httpClient)->createParcel($purchase);

        $this->assertIsString($capturedArg);
        $this->assertSame('52172', $capturedArg);
    }

    public function testCreateParcel_nonJsonResponse_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('<html>Bad Gateway</html>', ['http_code' => 502]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-JSON response (HTTP 502)');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testCreateParcel_emptyResponseBody_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(''));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testCreateParcel_usesProdUrlInProdEnvironment(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'prod')->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertStringStartsWith('https://shipping.dpdgroup.com/api/v1.1/', $capturedUrl);
    }

    public function testGetParcelStatus_statusCodeMinus1_returnsCancelled(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse(52172, -1)));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame(ParcelDeliveryStateEnum::CANCELLED, $result->state);
        $this->assertTrue($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode0_draft_returnsReceivedData(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse(52172, 0)));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode1_withPickup_returnsReceivedData(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse(52172, 1)));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode2_printed_returnsInTransit(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse(52172, 2)));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame(ParcelDeliveryStateEnum::IN_TRANSIT, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_unmappedCode_fallsBackToReceivedData(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse(52172, 99)));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_updateDateAndTimePresent_parsesOccurredAt(): void
    {
        // occurredAt was never asserted anywhere before this.
        $response = json_encode([
            'transactionId' => 4399,
            'shipment' => ['shipmentId' => 52172, 'status' => 2, 'updateDate' => '20260615', 'updateTime' => '143000'],
        ]);
        $httpClient = new MockHttpClient(new MockResponse($response));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertNotNull($result->occurredAt);
        $this->assertSame('2026-06-15 14:30:00', $result->occurredAt->format('Y-m-d H:i:s'));
    }

    public function testGetParcelStatus_updateDateMissing_occurredAtIsNull(): void
    {
        $response = json_encode([
            'transactionId' => 4399,
            'shipment' => ['shipmentId' => 52172, 'status' => 2, 'updateTime' => '143000'],
        ]);
        $httpClient = new MockHttpClient(new MockResponse($response));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertNull($result->occurredAt);
    }

    public function testGetParcelStatus_updateTimeMissing_occurredAtIsNull(): void
    {
        $response = json_encode([
            'transactionId' => 4399,
            'shipment' => ['shipmentId' => 52172, 'status' => 2, 'updateDate' => '20260615'],
        ]);
        $httpClient = new MockHttpClient(new MockResponse($response));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertNull($result->occurredAt);
    }

    public function testGetParcelStatus_unparsableUpdateDate_occurredAtIsNullNotException(): void
    {
        $response = json_encode([
            'transactionId' => 4399,
            'shipment' => ['shipmentId' => 52172, 'status' => 2, 'updateDate' => 'not-a-date', 'updateTime' => '143000'],
        ]);
        $httpClient = new MockHttpClient(new MockResponse($response));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertNull($result->occurredAt);
    }

    public function testGetParcelStatus_statusIsReadAsInteger(): void
    {
        // (int)($shipment['status'] ?? 0) — verify a numeric-string status still maps correctly.
        $response = json_encode([
            'transactionId' => 4399,
            'shipment' => ['shipmentId' => 52172, 'status' => '2'],
        ]);
        $httpClient = new MockHttpClient(new MockResponse($response));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame(ParcelDeliveryStateEnum::IN_TRANSIT, $result->state);
    }

    public function testGetParcelStatus_missingTransportation_fallsBackToEmptyJwt(): void
    {
        $capturedHeaders = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedHeaders) {
            $capturedHeaders = $options['headers'] ?? [];
            return new MockResponse(self::statusResponse(52172, 2));
        });

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(123);
        $purchase->method('getTransportation')->willReturn(null);
        $purchase->method('getShipmentId')->willReturn('52172');

        $this->makeService($httpClient)->getParcelStatus($purchase);

        $this->assertContains('Authorization: Bearer ', $capturedHeaders);
    }

    public function testGetParcelStatus_shipmentNotFound_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['transactionId' => 1])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testGetParcelStatus_nonJsonResponse_throwsRuntimeExceptionWithStatusCode(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('Not Found', ['http_code' => 404]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-JSON response (HTTP 404)');

        $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testGetParcelStatus_emptyResponseBody_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(''));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testGetParcelStatus_sendsGetRequestToShipmentIdEndpoint(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;
            return new MockResponse(self::statusResponse(52172, 2));
        });

        $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('jwt123'), shipmentId: '52172')
        );

        $this->assertSame('GET', $capturedMethod);
        $this->assertStringEndsWith('shipments/52172', $capturedUrl);
        $this->assertArrayNotHasKey('body', $capturedOptions);
    }

    public function testSupports_whenEnabled_returnsTrueForDpd(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertTrue($service->supports(TransportationAPI::DPD));
    }

    public function testSupports_whenDisabled_returnsFalse(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: false);

        $this->assertFalse($service->supports(TransportationAPI::DPD));
    }

    public function testSupports_neverMatchesOtherCarrier(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertFalse($service->supports(TransportationAPI::PACKETA));
    }
}
