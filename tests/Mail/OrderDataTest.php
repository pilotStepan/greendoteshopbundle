<?php

namespace Greendot\EshopBundle\Tests\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Greendot\EshopBundle\Mail\Data\OrderData;
use Greendot\EshopBundle\Money\Money;

class OrderDataTest extends TestCase
{
    /**
     * The checkout email can be rendered before a voucher-covered order is settled (orderPaid
     * still false), so a non-positive total alone must already mean "nothing to pay".
     */
    #[DataProvider('nothingToPayProvider')]
    public function testNothingToPay(bool $orderPaid, ?Money $totalMoney, bool $expected): void
    {
        // Only the two fields nothingToPay() reads matter; skip the constructor's many nested DTOs.
        $orderData = (new \ReflectionClass(OrderData::class))->newInstanceWithoutConstructor();
        $orderData->orderPaid = $orderPaid;
        $orderData->totalMoney = $totalMoney;

        $this->assertSame($expected, $orderData->nothingToPay());
    }

    public static function nothingToPayProvider(): iterable
    {
        yield 'paid' => [true, new Money(150.0, 'CZK'), true];
        yield 'unpaid, covered by voucher' => [false, new Money(0.0, 'CZK'), true];
        yield 'unpaid, to pay' => [false, new Money(150.0, 'CZK'), false];
        yield 'unpaid, total unknown' => [false, null, false];
    }
}
