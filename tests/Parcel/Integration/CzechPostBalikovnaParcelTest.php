<?php

namespace Greendot\EshopBundle\Tests\Parcel\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Parcel\Integration\CzechPostBalikovnaParcel;
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

class CzechPostBalikovnaParcelTest extends TestCase
{
    private function makeTransportation(string $secretKeyBase64, ?string $token = 'api-token-123'): Transportation
    {
        $t = $this->createMock(Transportation::class);
        $t->method('getSecretKey')->willReturn($secretKeyBase64);
        $t->method('getToken')->willReturn($token);
        return $t;
    }

    private function makeBranch(
        string $street = 'Balíkovna ulice 5',
        string $city = 'Brno',
        string $zip = '60200',
    ): Branch {
        $b = $this->createMock(Branch::class);
        $b->method('getProviderId')->willReturn('czechpost_10000');
        $b->method('getStreet')->willReturn($street);
        $b->method('getCity')->willReturn($city);
        $b->method('getZip')->willReturn($zip);
        return $b;
    }

    private function makeAddress(): PurchaseAddress
    {
        $a = $this->createMock(PurchaseAddress::class);
        $a->method('getShipName')->willReturn(null);
        $a->method('getShipSurname')->willReturn(null);
        $a->method('getShipCompany')->willReturn(null);
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
        Branch $branch,
        bool $isCod = false,
        string $transportNumber = 'NB1234567890A',
    ): Purchase {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(42);
        $client->method('getName')->willReturn('John');
        $client->method('getSurname')->willReturn('Doe');
        $client->method('getMail')->willReturn('john@example.com');
        $client->method('getPhone')->willReturn('+420777123456');

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn(123);
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

    private function makeService(MockHttpClient $httpClient, bool $enabled = true, string $environment = 'test'): CzechPostBalikovnaParcel
    {
        return new CzechPostBalikovnaParcel(
            $httpClient,
            new NullLogger(),
            $this->makePriceFactory(),
            $this->makeCurrencyRepo(),
            'M06391',
            '18000',
            'Jana Smažíková',
            $environment,
            $enabled,
        );
    }

    private static function successResponse(string $parcelCode = 'NB1234567890A'): string
    {
        return json_encode([
            'responseHeader' => [
                'resultParcelData' => [
                    ['recordNumber' => '1', 'parcelCode' => $parcelCode],
                ],
            ],
        ]);
    }

    private static function statusResponse(string $statusId, string $reasonId = '1', string $date = '2024-01-16T10:00:00+01:00'): string
    {
        return json_encode([
            'idParcel' => 'NB1234567890A',
            'parcelStatus' => [
                'statusID' => $statusId,
                'reasonID' => $reasonId,
                'date' => $date,
                'statusDescription' => 'desc',
            ],
        ]);
    }

    public function testCreateParcel_returnsParcelCode(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::successResponse('NB1234567890A')));

        $result = $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $this->assertSame('NB1234567890A', $result);
    }

    public function testCreateParcel_postsToCzParcelsEndpoint(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'prod')->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $this->assertSame('https://b2b.postaonline.cz:444/restservices/ZSKSService/v1/cz/parcels', $capturedUrl);
    }

    public function testCreateParcel_usesSandboxUrlInTestEnvironment(): void
    {
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient, environment: 'test')->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $this->assertStringStartsWith('https://b2b-test.postaonline.cz:444/restservices/ZSKSService/v1/', $capturedUrl);
    }

    public function testCreateParcel_usesPrefixNbAndBranchAddress(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParam = $decoded['parcelData']['parcelParams'][0];
        $address = $decoded['parcelData']['parcelAddress']['address'];

        $this->assertSame('NB', $parcelParam['prefixParcelCode']);
        $this->assertSame('Balíkovna ulice 5', $address['street']);
        $this->assertSame('Brno', $address['city']);
        $this->assertSame('60200', $address['zipCode']);
    }

    public function testCreateParcel_sendsCustomerIdPostCodeAndSenderCompanyName(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $decoded = json_decode($capturedBody, true);
        $header = $decoded['parcelHeader'];

        $this->assertSame('M06391', $header['customerId']);
        $this->assertSame('18000', $header['postCode']);
        $this->assertSame('Jana Smažíková', $header['sender']['companyName']);
    }

    public function testCreateParcel_codOrder_includesCodServiceCode(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch(), isCod: true)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParam = $decoded['parcelData']['parcelParams'][0];

        $this->assertEquals(500.0, $parcelParam['amount']);
        $this->assertSame('123', $parcelParam['vsVoucher']);
        $this->assertContains(['service' => '41'], $parcelParam['services']);
    }

    public function testCreateParcel_nonCodOrder_excludesCodServiceCode(): void
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'];
            return new MockResponse(self::successResponse());
        });

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch(), isCod: false)
        );

        $decoded = json_decode($capturedBody, true);
        $parcelParam = $decoded['parcelData']['parcelParams'][0];

        $this->assertSame(0, $parcelParam['amount']);
        $this->assertSame('', $parcelParam['vsVoucher']);
        $this->assertNotContains(['service' => '41'], $parcelParam['services']);
    }

    public function testCreateParcel_noBranch_throwsInvalidArgumentException(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getTransportation')->willReturn($this->makeTransportation('c2VjcmV0'));
        $purchase->method('getBranch')->willReturn(null);
        $purchase->method('getId')->willReturn(123);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService(new MockHttpClient())->createParcel($purchase);
    }

    public function testCreateParcel_missingParcelCode_throwsRuntimeException(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['responseHeader' => ['resultParcelData' => []]])));

        $this->expectException(RuntimeException::class);

        $this->makeService($httpClient)->createParcel(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );
    }

    public function testGetParcelStatus_sendsGetRequestToZskServiceIdParcelEndpoint(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$capturedMethod, &$capturedUrl) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            return new MockResponse(self::statusResponse('2'));
        });

        $this->makeService($httpClient, environment: 'prod')->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch(), transportNumber: 'NB1234567890A')
        );

        $this->assertSame('GET', $capturedMethod);
        $this->assertSame(
            'https://b2b.postaonline.cz:444/restservices/ZSKService/v1/parcelStatuses/current/idParcel/NB1234567890A',
            $capturedUrl
        );
    }

    public function testGetParcelStatus_statusId3_returnsReadyForPickup(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse('3')));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $this->assertSame(ParcelDeliveryStateEnum::READY_FOR_PICKUP, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testGetParcelStatus_unmappedCode_fallsBackToReceivedData(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::statusResponse('999')));

        $result = $this->makeService($httpClient)->getParcelStatus(
            $this->makePurchase($this->makeTransportation('c2VjcmV0'), $this->makeBranch())
        );

        $this->assertSame(ParcelDeliveryStateEnum::RECEIVED_DATA, $result->state);
        $this->assertFalse($result->state->isFinal());
    }

    public function testSupports_whenEnabled_returnsTrueForBalikovnaOnly(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertTrue($service->supports(TransportationAPI::CP_BALIKOVNA));
        $this->assertFalse($service->supports(TransportationAPI::CP_DO_RUKY));
    }

    public function testSupports_whenDisabled_returnsFalse(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: false);

        $this->assertFalse($service->supports(TransportationAPI::CP_BALIKOVNA));
    }

    public function testSupports_neverMatchesOtherCarrier(): void
    {
        $service = $this->makeService(new MockHttpClient(), enabled: true);

        $this->assertFalse($service->supports(TransportationAPI::DPD));
        $this->assertFalse($service->supports(TransportationAPI::PACKETA));
    }
}
