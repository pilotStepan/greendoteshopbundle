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
        $a->method('getShipZip')->willReturn('10000');
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
        string $transportNumber = '52172',
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
        $purchase->method('getPurchaseAddress')->willReturn($this->makeAddress($country));
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

    private function makeService(MockHttpClient $httpClient, string $environment = 'test', bool $enabled = true): DpdParcel
    {
        return new DpdParcel(
            $httpClient,
            new NullLogger(),
            $this->makePriceFactory(),
            $this->makeCurrencyRepo(),
            '021',
            'TestCustomer',
            '56',
            $environment,
            $enabled,
        );
    }

    private static function successResponse(int $shipmentId = 52172, int $status = 2): string
    {
        return json_encode([
            'transactionId' => 4383,
            'shipmentResults' => [
                [
                    'numOrder' => 1,
                    'shipment' => [
                        'shipmentId' => $shipmentId,
                        'status' => $status,
                    ],
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

    public function testCreateParcel_returnsShipmentId(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::successResponse(52172)));

        $result = $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertSame('52172', $result);
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

    public function testCreateParcel_sendsBuCodeCustomerIdAndSenderAddressId(): void
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
        $this->assertSame('021', $decoded['buCode']);
        $this->assertSame('TestCustomer', $decoded['customerId']);
        $this->assertSame(56, $decoded['shipments'][0]['senderAddressId']);
        $this->assertSame('Praha', $decoded['shipments'][0]['receiver']['city']);
        $this->assertSame('10000', $decoded['shipments'][0]['receiver']['zipCode']);
        $this->assertSame('Testovací 123', $decoded['shipments'][0]['receiver']['street']);
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
        $this->assertSame('CZK', $decoded['shipments'][0]['service']['additionalService']['cod']['currency']);
    }

    public function testCreateParcel_nonCodOrder_excludesService(): void
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
        $this->assertArrayNotHasKey('service', $decoded['shipments'][0]);
    }

    public function testCreateParcel_missingShipmentId_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['transactionId' => 1, 'shipmentResults' => []])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );
    }

    public function testCreateParcel_usesSandboxUrlInTestEnvironment(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'test')->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertStringStartsWith('https://nst-preprod.dpsin.dpdgroup.com/api/v1.1/', $capturedUrl);
    }

    public function testCreateParcel_usesSandboxUrlInDevEnvironment(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'dev')->createParcel(
            $this->makePurchase($this->makeTransportation('jwt123'))
        );

        $this->assertStringStartsWith('https://nst-preprod.dpsin.dpdgroup.com/api/v1.1/', $capturedUrl);
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

    public function testGetParcelStatus_shipmentNotFound_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['transactionId' => 1])));

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
            $this->makePurchase($this->makeTransportation('jwt123'), transportNumber: '52172')
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
