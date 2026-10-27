<?php

namespace Greendot\EshopBundle\Tests\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Factory\UserDataFactory;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserDataFactoryTest extends TestCase
{
    public function testDisabledFactoryReturnsNull(): void
    {
        self::assertNull((new UserDataFactory(false))->create($this->createPurchase()));
    }

    public function testCreateHashesNormalizedContactData(): void
    {
        $userData = (new UserDataFactory(true))->create($this->createPurchase());

        self::assertSame(hash('sha256', 'jana.novakova@seznam.cz'), $userData['sha256_email_address']);
        self::assertSame(hash('sha256', '+420739834430'), $userData['sha256_phone_number']);
        self::assertSame([
            'sha256_first_name' => hash('sha256', 'jana'),
            'sha256_last_name'  => hash('sha256', 'nováková'),
            'postal_code'       => '11000',
            'country'           => 'CZ',
        ], $userData['address']);
    }

    public function testAddressIsLeftOutWhenIncomplete(): void
    {
        $userData = (new UserDataFactory(true))->create($this->createPurchase(zip: null));

        self::assertArrayNotHasKey('address', $userData);
        self::assertArrayHasKey('sha256_email_address', $userData);
    }

    public function testPurchaseWithoutContactDataReturnsNull(): void
    {
        $purchase = $this->createPurchase(mail: null, phone: null, name: null);

        self::assertNull((new UserDataFactory(true))->create($purchase));
    }

    #[DataProvider('emailProvider')]
    public function testNormalizeEmail(?string $input, ?string $expected): void
    {
        self::assertSame($expected, (new UserDataFactory())->normalizeEmail($input));
    }

    public static function emailProvider(): iterable
    {
        yield 'trim and lowercase' => ['  Jana.Novakova@Seznam.CZ ', 'jana.novakova@seznam.cz'];
        yield 'gmail dots removed' => ['jana.nova.kova@gmail.com', 'jananovakova@gmail.com'];
        yield 'googlemail dots removed' => ['j.n@googlemail.com', 'jn@googlemail.com'];
        yield 'invalid' => ['not-an-email', null];
        yield 'empty' => [null, null];
    }

    #[DataProvider('phoneProvider')]
    public function testNormalizePhone(?string $input, ?string $country, ?string $expected): void
    {
        self::assertSame($expected, (new UserDataFactory())->normalizePhone($input, $country));
    }

    public static function phoneProvider(): iterable
    {
        yield 'international with spaces' => ['+420 739 834 430', 'CZ', '+420739834430'];
        yield '00 prefix' => ['00421 905 123 456', 'CZ', '+421905123456'];
        yield 'national CZ' => ['739 834 430', 'CZ', '+420739834430'];
        yield 'national SK with trunk zero' => ['0905 123 456', 'SK', '+421905123456'];
        yield 'national, unknown country' => ['739834430', 'FR', null];
        yield 'national, no country' => ['739834430', null, null];
        yield 'too short' => ['+42012', 'CZ', null];
        yield 'empty' => ['', 'CZ', null];
    }

    private function createPurchase(
        ?string $mail = ' Jana.Novakova@Seznam.cz',
        ?string $phone = '739 834 430',
        ?string $name = 'Jana',
        ?string $zip = '110 00',
    ): Purchase {
        $client = $this->createMock(Client::class);
        $client->method('getMail')->willReturn($mail);
        $client->method('getPhone')->willReturn($phone);
        $client->method('getName')->willReturn($name);
        $client->method('getSurname')->willReturn('Nováková');

        $address = $this->createMock(PurchaseAddress::class);
        $address->method('getZip')->willReturn($zip);
        $address->method('getCountry')->willReturn('cz');

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPurchaseAddress')->willReturn($address);

        return $purchase;
    }
}
