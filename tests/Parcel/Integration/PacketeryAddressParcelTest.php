<?php

namespace Greendot\EshopBundle\Tests\Parcel\Integration;

use RuntimeException;
use Psr\Log\NullLogger;
use Greendot\EshopBundle\Parcel\Exception\PermanentParcelException;
use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\HttpClient\MockHttpClient;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Parcel\Integration\PacketeryAddressParcel;

class PacketeryAddressParcelTest extends TestCase
{
    public function testCreateParcel_usesTokenAsAddressIdAndSendsAddress(): void
    {
        $capturedBodies = [];
        $httpClient = $this->makeFullFlowHttpClient(function (string $body) use (&$capturedBodies) {
            $capturedBodies[] = $body;
        });

        $transportation = $this->makeTransportation('apiPw', '131');

        $result = $this->makeService($httpClient)->createParcel(
            $this->makePurchase($transportation),
        );

        $this->assertSame('Z4154090000', $result);
        $this->assertStringContainsString('<addressId>131</addressId>', $capturedBodies[0]);
        $this->assertStringContainsString('<city>Bratislava</city>', $capturedBodies[0]);
        $this->assertStringContainsString('<zip>81101</zip>', $capturedBodies[0]);
        $this->assertStringContainsString('<street>Testovacia 123</street>', $capturedBodies[0]);
    }

    /**
     * MockHttpClient that serves a successful createPacket response.
     * The courier-number / label follow-up calls are disabled (fetchCourierTrackingAndLabel
     * commented out), so only one request is made per createParcel call.
     */
    private function makeFullFlowHttpClient(?callable $onEachRequest = null): MockHttpClient
    {
        $responses = [
            new MockResponse(self::successCreateXml()),
        ];

        return new MockHttpClient(function (string $method, string $url, array $options) use (&$responses, $onEachRequest) {
            if ($onEachRequest !== null) {
                $onEachRequest($options['body']);
            }
            return array_shift($responses);
        });
    }

    private static function successCreateXml(string $barcode = 'Z4154090000'): string
    {
        return "<response><status>ok</status><result><id>4154090000</id><barcode>$barcode</barcode><barcodeText>Z 415 4090 000</barcodeText></result></response>";
    }

    private static function successCourierNumberXml(string $courierNumber = 'CN123456'): string
    {
        return "<response><status>ok</status><result><courierNumber>$courierNumber</courierNumber><carrierId>131</carrierId><carrierName>SK Packeta Home HD</carrierName></result></response>";
    }

    private static function successCourierLabelXml(): string
    {
        return '<response><status>ok</status><result>BASE64PDFCONTENT</result></response>';
    }

    private function makeTransportation(string $secretKey, string $token): Transportation
    {
        $t = $this->createMock(Transportation::class);
        $t->method('getSecretKey')->willReturn($secretKey);
        $t->method('getToken')->willReturn($token);
        return $t;
    }

    private function makeService(MockHttpClient $httpClient, float $price = 500.0, bool $enabled = true): PacketeryAddressParcel
    {
        return new PacketeryAddressParcel(
            $httpClient,
            new NullLogger(),
            $this->makePriceFactory($price),
            $this->makeCurrencyRepo(),
            'TestEshop',
            $enabled,
        );
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

    private function makePurchase(
        Transportation   $transportation,
        bool             $isCod = false,
        string           $country = 'sk',
        ?PurchaseAddress $address = null,
    ): Purchase
    {
        $client = $this->createMock(Client::class);
        $client->method('getName')->willReturn('John');
        $client->method('getSurname')->willReturn('Doe');
        $client->method('getMail')->willReturn('john@example.com');
        $client->method('getPhone')->willReturn('+421777123456');

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(123);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPurchaseAddress')->willReturn($address ?? $this->makeAddress($country));
        $purchase->method('getPaymentType')->willReturn($this->makePaymentType($isCod));
        $purchase->method('getTransportNumber')->willReturn('Z4154090000');
        $purchase->method('isVatExempted')->willReturn(false);
        return $purchase;
    }

    private function makeAddress(string $country = 'sk'): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getCountry')->willReturn($country);
        $a->method('getStreet')->willReturn('Testovacia 123');
        $a->method('getCity')->willReturn('Bratislava');
        $a->method('getZip')->willReturn('81101');
        return $a;
    }

    private function makePaymentType(bool $isCod): PaymentType
    {
        $p = $this->createMock(PaymentType::class);
        $p->method('getActionGroup')->willReturn(
            $isCod ? PaymentTypeActionGroup::ON_DELIVERY : PaymentTypeActionGroup::CARD_PAYMENT,
        );
        return $p;
    }

    public function testCreateParcel_shipOverride_addressesPacketToRecipientNotOrderer(): void
    {
        $capturedBodies = [];
        $httpClient = $this->makeFullFlowHttpClient(function (string $body) use (&$capturedBodies) {
            $capturedBodies[] = $body;
        });

        $transportation = $this->makeTransportation('apiPw', '131');

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($transportation, address: $this->makeAddressWithShipOverride()),
        );

        $this->assertStringContainsString('<name>Jane</name>', $capturedBodies[0]);
        $this->assertStringContainsString('<surname>Smith</surname>', $capturedBodies[0]);
        $this->assertStringContainsString('<street>Hlavna 1</street>', $capturedBodies[0]);
        $this->assertStringContainsString('<city>Kosice</city>', $capturedBodies[0]);
        $this->assertStringContainsString('<zip>04001</zip>', $capturedBodies[0]);
    }

    /**
     * Customer specified a separate shipping address (ship_* override fields) different
     * from the billing address, so the packet must be addressed to the actual recipient.
     */
    private function makeAddressWithShipOverride(string $country = 'sk'): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getCountry')->willReturn($country);
        $a->method('getStreet')->willReturn('Testovacia 123');
        $a->method('getCity')->willReturn('Bratislava');
        $a->method('getZip')->willReturn('81101');
        $a->method('getShipName')->willReturn('Jane');
        $a->method('getShipSurname')->willReturn('Smith');
        $a->method('getShipStreet')->willReturn('Hlavna 1');
        $a->method('getShipCity')->willReturn('Kosice');
        $a->method('getShipZip')->willReturn('04001');
        return $a;
    }

    public function testCreateParcel_onlyCallsCreatePacket_courierFollowUpDisabled(): void
    {
        $capturedBodies = [];
        $httpClient = $this->makeFullFlowHttpClient(function (string $body) use (&$capturedBodies) {
            $capturedBodies[] = $body;
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('apiPw', '131')),
        );

        // Only createPacket — courier-number/label follow-up is disabled.
        $this->assertCount(1, $capturedBodies);
        $this->assertStringContainsString('<createPacket>', $capturedBodies[0]);
    }

    public function testCreateParcel_doesNotSetCourierNumber_followUpDisabled(): void
    {
        $httpClient = $this->makeFullFlowHttpClient();
        $purchase = $this->makePurchase($this->makeTransportation('apiPw', '131'));

        $purchase->expects($this->never())->method('setCourierNumber');

        $this->makeService($httpClient)->createParcel($purchase);
    }

    public function testCreateParcel_packetAttributesFault_throwsPermanentParcelException(): void
    {
        $xml = '<response><status>fault</status><fault>PacketAttributesFault</fault><string>Failed to validate attributes. See detail.</string><detail><attributes><fault><name>addressId</name><fault>Invalid branch.</fault></fault></attributes></detail></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $this->expectException(PermanentParcelException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw', '131')),
        );
    }

    public function testCreateParcel_codOrder_includesCod(): void
    {
        $httpClient = $this->makeFullFlowHttpClient(function (string $body) use (&$capturedBody) {
            $capturedBody ??= $body;
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw', '131'), isCod: true),
        );

        $this->assertStringContainsString('<cod>', $capturedBody);
    }

    public function testCreateParcel_nonCodOrder_excludesCod(): void
    {
        $httpClient = $this->makeFullFlowHttpClient(function (string $body) use (&$capturedBody) {
            $capturedBody ??= $body;
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw', '131'), isCod: false),
        );

        $this->assertStringContainsString('<currency>EUR</currency>', $capturedBody);
        $this->assertStringNotContainsString('<cod>', $capturedBody);
    }

    public function testCreateParcel_eshopNameInjectedFromConfig(): void
    {
        $httpClient = $this->makeFullFlowHttpClient(function (string $body) use (&$capturedBody) {
            $capturedBody ??= $body;
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('pw', '131')),
        );

        $this->assertStringContainsString('<eshop_id>TestEshop</eshop_id>', $capturedBody);
    }

    public function testCreateParcel_apiError_throwsRuntimeException(): void
    {
        $xml = '<response><status>error</status><fault><code>1</code><message>Invalid API password</message></fault></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('badpw', '131')),
        );
    }

    public function testGetParcelStatus_statusCode1_returnsReceivedData(): void
    {
        $xml = '<response><status>ok</status><result><dateTime>2024-01-06T11:43:09</dateTime><statusCode>1</statusCode><codeText>received data</codeText></result></response>';
        $httpClient = new MockHttpClient(new MockResponse($xml));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('pw', '131')),
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
    }

    public function testSupports_whenEnabled_returnsTrueForPacketaAddress(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertTrue($service->supports(TransportationAPI::PACKETA_ADDRESS));
    }

    public function testSupports_whenDisabled_returnsFalse(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: false);

        $this->assertFalse($service->supports(TransportationAPI::PACKETA_ADDRESS));
    }

    public function testSupports_neverMatchesPickupPointPacketa(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertFalse($service->supports(TransportationAPI::PACKETA));
    }
}
