<?php

namespace Greendot\EshopBundle\Tests\Money;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Money\Exception\CurrencyMismatchException;
use Greendot\EshopBundle\Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testConstructorStoresValueAndIso(): void
    {
        $money = new Money(123.45, 'CZK');

        $this->assertSame(123.45, $money->getValue());
        $this->assertSame('CZK', $money->getIso());
    }

    public function testFromCurrencyUsesCurrencyIso(): void
    {
        $currency = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);

        $money = Money::fromCurrency(10.5, $currency);

        $this->assertSame(10.5, $money->getValue());
        $this->assertSame('EUR', $money->getIso());
    }

    public function testFromCurrencyTreatsNullValueAsZero(): void
    {
        $currency = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);

        $money = Money::fromCurrency(null, $currency);

        $this->assertSame(0.0, $money->getValue());
    }

    public function testZeroFromIsoString(): void
    {
        $money = Money::zero('CZK');

        $this->assertTrue($money->isZero());
        $this->assertSame('CZK', $money->getIso());
    }

    public function testAddSameCurrency(): void
    {
        $sum = (new Money(10.0, 'CZK'))->add(new Money(5.0, 'CZK'));

        $this->assertSame(15.0, $sum->getValue());
        $this->assertSame('CZK', $sum->getIso());
    }

    public function testSubtractSameCurrency(): void
    {
        $diff = (new Money(10.0, 'CZK'))->subtract(new Money(4.0, 'CZK'));

        $this->assertSame(6.0, $diff->getValue());
    }

    public function testMultiply(): void
    {
        $result = (new Money(10.0, 'CZK'))->multiply(1.5);

        $this->assertSame(15.0, $result->getValue());
        $this->assertSame('CZK', $result->getIso());
    }

    public function testEqualsSameValueAndCurrency(): void
    {
        $this->assertTrue((new Money(10.0, 'CZK'))->equals(new Money(10.0, 'CZK')));
        $this->assertFalse((new Money(10.0, 'CZK'))->equals(new Money(10.1, 'CZK')));
    }

    public function testIsSameCurrency(): void
    {
        $this->assertTrue((new Money(1.0, 'CZK'))->isSameCurrency(new Money(2.0, 'CZK')));
        $this->assertFalse((new Money(1.0, 'CZK'))->isSameCurrency(new Money(2.0, 'EUR')));
    }

    public function testAddDifferentCurrencyThrows(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        (new Money(10.0, 'CZK'))->add(new Money(5.0, 'EUR'));
    }

    public function testSubtractDifferentCurrencyThrows(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        (new Money(10.0, 'CZK'))->subtract(new Money(5.0, 'EUR'));
    }

    public function testEqualsDifferentCurrencyThrows(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        (new Money(10.0, 'CZK'))->equals(new Money(10.0, 'EUR'));
    }

    public function testJsonSerializeShapeIsValueAndIsoOnly(): void
    {
        $money = new Money(1234.5, 'CZK');

        $this->assertSame(
            ['value' => 1234.5, 'iso' => 'CZK'],
            json_decode(json_encode($money), true),
        );
    }
}
