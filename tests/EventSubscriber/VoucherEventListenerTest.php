<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\EventSubscriber\VoucherEventListener;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

class VoucherEventListenerTest extends TestCase
{
    public function testPostLoadSetsAmountMoneyInShopDefaultCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);

        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->with(['isDefault' => true])->willReturn($czk);

        $listener = new VoucherEventListener($currencyRepository);

        $voucher = (new Voucher())->setAmount(1200);
        $listener->postLoad($voucher);

        $this->assertNotNull($voucher->getAmountMoney());
        $this->assertSame(1200.0, $voucher->getAmountMoney()->getValue());
        $this->assertSame('CZK', $voucher->getAmountMoney()->getIso());
    }

    /**
     * Regression test: a voucher's raw amount is always denominated in the shop's default/base
     * currency, regardless of what currency the current viewer/session has selected. Tagging it
     * with the ambient session currency instead (as an earlier version of this listener did)
     * relabels the same CZK number as EUR without converting it - a silent value/currency
     * mismatch, exactly like the amount showing "1200 EUR" for a voucher actually worth 1200 CZK.
     */
    public function testAmountMoneyIgnoresAmbientSessionCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        // Note: no CurrencyManager/session dependency at all - the listener must not consult it.

        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->with(['isDefault' => true])->willReturn($czk);

        $listener = new VoucherEventListener($currencyRepository);

        $voucher = (new Voucher())->setAmount(1200);
        $listener->postLoad($voucher);

        $this->assertSame(1200.0, $voucher->getAmountMoney()->getValue());
        $this->assertSame('CZK', $voucher->getAmountMoney()->getIso());
    }

    public function testPostLoadLeavesAmountMoneyNullWhenAmountIsNull(): void
    {
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->expects($this->never())->method('findOneBy');

        $listener = new VoucherEventListener($currencyRepository);

        $voucher = new Voucher(); // amount left null

        $listener->postLoad($voucher);

        $this->assertNull($voucher->getAmountMoney());
    }
}
