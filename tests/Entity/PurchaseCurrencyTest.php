<?php

namespace Greendot\EshopBundle\Tests\Entity;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use PHPUnit\Framework\TestCase;

class PurchaseCurrencyTest extends TestCase
{
    public function testSetPaymentTypeWithCurrencySetsPurchaseCurrency(): void
    {
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);
        $paymentType = (new PaymentType())->setCurrency($eur);

        $purchase = (new Purchase())->setPaymentType($paymentType);

        $this->assertSame($eur, $purchase->getCurrency());
    }

    public function testSetPaymentTypeWithoutCurrencyLeavesPurchaseCurrencyUnchanged(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);
        $purchase = (new Purchase())->setCurrency($czk);

        $paymentType = new PaymentType(); // no currency set

        $purchase->setPaymentType($paymentType);

        $this->assertSame($czk, $purchase->getCurrency());
    }

    public function testSwitchingToPaymentTypeWithDifferentCurrencyUpdatesPurchaseCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);

        $purchase = (new Purchase())->setCurrency($czk);
        $skPaymentType = (new PaymentType())->setCurrency($eur);

        $purchase->setPaymentType($skPaymentType);

        $this->assertSame($eur, $purchase->getCurrency());
    }

    public function testSetPaymentTypeAfterCheckoutDoesNotChangePurchaseCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);

        $purchase = (new Purchase())->setCurrency($czk);
        $purchase->setMarking(['received' => 1]); // no longer draft/cart/wishlist

        $eurPaymentType = (new PaymentType())->setCurrency($eur);
        $purchase->setPaymentType($eurPaymentType);

        $this->assertSame(
            $czk,
            $purchase->getCurrency(),
            'Currency snapshot must stay frozen once a purchase has left draft/cart/wishlist.',
        );
        $this->assertSame($eurPaymentType, $purchase->getPaymentType());
    }

    /**
     * Regression test: setPaymentType() used to also treat a NULL currency as "pre-checkout",
     * regardless of place, so a purchase whose currency was never stamped (e.g. legacy data, or a
     * bug elsewhere) got retroactively stamped from whatever PaymentType was assigned to it -
     * even post-checkout, where PurchaseStateSubscriber::onPayment() reassigns PaymentType with no
     * validator running. Currency is now decided exactly once, by place alone (checkout/init_order
     * via ManagePurchase::ensureCurrency()); a NULL currency past that point must stay a visible
     * defect for PurchaseStateSubscriber's currency-consistency guard to catch, not get silently
     * patched over here.
     */
    public function testSetPaymentTypeAfterCheckoutDoesNotBackfillNullCurrency(): void
    {
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);

        $purchase = new Purchase();
        $purchase->setMarking(['received' => 1]); // no longer draft/cart/wishlist
        // currency was never stamped - stays null

        $eurPaymentType = (new PaymentType())->setCurrency($eur);
        $purchase->setPaymentType($eurPaymentType);

        $this->assertNull(
            $purchase->getCurrency(),
            'A NULL currency past checkout/init_order must stay NULL, not get silently backfilled.',
        );
    }
}
