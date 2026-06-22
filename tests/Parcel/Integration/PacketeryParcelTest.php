<?php

namespace Greendot\EshopBundle\Tests\Parcel\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Parcel\Integration\PacketeryParcel;
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

class PacketeryParcelTest extends TestCase
{
    private function makeTransportation(string $secretKey, ?string $token = null): Transportation
    {
        $t = $this->createMock(Transportation::class);
        $t->method('getSecretKey')->willReturn($secretKey);
        $t->method('getToken')->willReturn($token);
        return $t;
    }

    private function makeAddress(string $country = 'cz'): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getCountry')->willReturn($country);
        $a->method('getStreet')->willReturn('Testovací 123');
        $a->method('getCity')->willReturn('Praha');
        $a->method('getZip')->willReturn('10000');
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
        string $country = 'cz',
    ): Purchase {
        $client = $this->createMock(Client::class);
        $client->method('getName')->willReturn('John');
        $client->method('getSurname')->willReturn('Doe');
        $client->method('getMail')->willReturn('john@example.com');
        $client->method('getPhone')->willReturn('+420777123456');

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(123);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getBranch')->willReturn($branch);
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPurchaseAddress')->willReturn($this->makeAddress($country));
        $purchase->method('getPaymentType')->willReturn($this->makePaymentType($isCod));
        $purchase->method('getTransportNumber')->willReturn('Z4154090000');
        $purchase->method('isVatExempted')->willReturn(false);
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

    private function makeService(MockHttpClient $httpClient, float $price = 500.0, bool $enabled = true): PacketeryParcel
    {
        return new PacketeryParcel(
            $httpClient,
            new NullLogger(),
            $this->makePriceFactory($price),
            $this->makeCurrencyRepo(),
            'TestEshop',
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

    private function makeServiceWithCodAwarePricing(MockHttpClient $httpClient, bool $enabled = true): PacketeryParcel
    {
        return new PacketeryParcel(
            $httpClient,
            new NullLogger(),
            $this->makeCodAwarePriceFactory(),
            $this->makeCurrencyRepo(),
            'TestEshop',
            $enabled,
        );
    }

    private static function successXml(string $barcode = 'Z4154090000'): string
    {
        return "<response><status>ok</status><result><id>4154090000</id><barcode>$barcode</barcode><barcodeText>Z 415 4090 000</barcodeText></result></response>";
    }

    public function testCreateParcel_pickupPoint_returnsBarcode(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::successXml()));
        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_52');

        $result = $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('secret123'), $branch)
        );

        $this->assertSame('Z4154090000', $result);
    }

    public function testCreateParcel_pickupPoint_usesStrippedAddressId(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successXml());
        });

        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_99');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw'), $branch)
        );

        $this->assertStringContainsString('<addressId>99</addressId>', $capturedBody);
    }

    public function testCreateParcel_homeDelivery_usesTokenAsAddressIdAndSendsAddress(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successXml());
        });

        $transportation = $this->makeTransportation('apiPw', '106');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($transportation, null)
        );

        $this->assertStringContainsString('<addressId>106</addressId>', $capturedBody);
        $this->assertStringContainsString('<city>Praha</city>', $capturedBody);
        $this->assertStringContainsString('<zip>10000</zip>', $capturedBody);
        $this->assertStringContainsString('<street>Testovací 123</street>', $capturedBody);
    }

    public function testCreateParcel_codOrder_includesCod(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successXml());
        });

        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_52');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw'), $branch, isCod: true)
        );

        $this->assertStringContainsString('<cod>', $capturedBody);
    }

    public function testCreateParcel_codOrder_sendsCodAmountIncludingVatAndShipping(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successXml());
        });

        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_52');

        $this->makeServiceWithCodAwarePricing($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw'), $branch, isCod: true)
        );

        $this->assertStringContainsString('<cod>353.94</cod>', $capturedBody);
        // Declared package value must stay VAT-and-shipping-exclusive (goods value only).
        $this->assertStringContainsString('<value>214</value>', $capturedBody);
    }

    public function testCreateParcel_nonCodOrder_excludesCod(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successXml());
        });

        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_52');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw'), $branch, isCod: false)
        );

        $this->assertStringNotContainsString('<cod>', $capturedBody);
    }

    public function testCreateParcel_eshopNameInjectedFromConfig(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successXml());
        });

        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_52');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw'), $branch)
        );

        $this->assertStringContainsString('<eshop_id>TestEshop</eshop_id>', $capturedBody);
    }

    public function testCreateParcel_apiError_throwsRuntimeException(): void
    {
        $xml = '<response><status>error</status><fault><code>1</code><message>Invalid API password</message></fault></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));
        $branch = $this->createMock(Branch::class);
        $branch->method('getProviderId')->willReturn('packeta_52');

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('badpw'), $branch)
        );
    }

    public function testGetParcelStatus_statusCode1_returnsReceivedData(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-06T11:43:09</dateTime><statusCode>1</statusCode><codeText>received data</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode2_returnsInTransit(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-07T08:00:00</dateTime><statusCode>2</statusCode><codeText>on the way</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::IN_TRANSIT, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode3_returnsInTransit(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-07T12:44:43</dateTime><statusCode>3</statusCode><codeText>prepared for departure</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::IN_TRANSIT, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode4_returnsInTransit(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-08T09:15:00</dateTime><statusCode>4</statusCode><codeText>en route to destination</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::IN_TRANSIT, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode5_returnsReadyForPickup(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-10T10:43:00</dateTime><statusCode>5</statusCode><codeText>ready for pickup</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::READY_FOR_PICKUP, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode7_returnsDelivered(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-10T15:34:06</dateTime><statusCode>7</statusCode><codeText>delivered</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::DELIVERED, $result->state);
        $this->assertTrue($result->state->isFinal());
    }

    public function testGetParcelStatus_statusCode8_returnsNotPickedUp(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-20T00:00:00</dateTime><statusCode>8</statusCode><codeText>not picked up</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::NOT_PICKED_UP, $result->state);
        $this->assertTrue($result->state->isFinal());
    }

    public function testGetParcelStatus_unknownCode_returnsCancelled(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-20T00:00:00</dateTime><statusCode>99</statusCode><codeText>unknown</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw'), null)
        );

        $this->assertSame(ParcelDeliveryStateEnum::CANCELLED, $result->state);
        $this->assertTrue($result->state->isFinal());
    }

    public function testSupports_whenEnabled_returnsTrueForPacketa(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertTrue($service->supports(TransportationAPI::PACKETA));
    }

    public function testSupports_whenDisabled_returnsFalse(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: false);

        $this->assertFalse($service->supports(TransportationAPI::PACKETA));
    }

    public function testSupports_neverMatchesOtherCarrier(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertFalse($service->supports(TransportationAPI::DPD));
    }
}
