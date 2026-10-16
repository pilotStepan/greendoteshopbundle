<?php

namespace Greendot\EshopBundle\Tests\Service;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use PHPUnit\Framework\TestCase;

class PurchaseLocaleResolverTest extends TestCase
{
    private const DEFAULT_LOCALE = 'cs';

    private PurchaseLocaleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PurchaseLocaleResolver(self::DEFAULT_LOCALE);
    }

    public function testResolveReturnsClientLocaleWhenSet(): void
    {
        $client = (new Client())->setLocale('sk');

        $purchaseCurrency = (new Currency())->setDefaultLocale('de'); // should be ignored

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setCurrency($purchaseCurrency);

        $this->assertSame('sk', $this->resolver->resolve($purchase));
    }

    public function testResolveFallsBackToPurchaseCurrencyDefaultLocaleWhenClientHasNone(): void
    {
        $client = new Client(); // no locale set

        $purchaseCurrency = (new Currency())->setDefaultLocale('sk');

        $paymentTypeCurrency = (new Currency())->setDefaultLocale('de'); // should be ignored, Purchase::$currency wins
        $paymentType = new PaymentType();
        $paymentType->setCurrency($paymentTypeCurrency);

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setPaymentType($paymentType);
        $purchase->setCurrency($purchaseCurrency);

        $this->assertSame('sk', $this->resolver->resolve($purchase));
    }

    /**
     * Purchases created before Purchase::$currency existed have no snapshot of
     * their own; the resolver must still fall back to the PaymentType's currency.
     */
    public function testResolveFallsBackToPaymentTypeCurrencyDefaultLocaleWhenPurchaseHasNoCurrency(): void
    {
        $client = new Client(); // no locale set

        $paymentTypeCurrency = (new Currency())->setDefaultLocale('sk');
        $paymentType = new PaymentType();
        $paymentType->setCurrency($paymentTypeCurrency);

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setPaymentType($paymentType);

        $this->assertSame('sk', $this->resolver->resolve($purchase));
    }

    public function testResolveFallsBackToDefaultLocaleWhenNothingElseAvailable(): void
    {
        $purchase = new Purchase(); // no client, no currency, no payment type

        $this->assertSame(self::DEFAULT_LOCALE, $this->resolver->resolve($purchase));
    }

    /**
     * An empty string is not a valid locale; it must be treated as "not set"
     * at every step of the chain, not returned verbatim.
     */
    public function testResolveTreatsEmptyStringLocaleAsAbsent(): void
    {
        $client = (new Client())->setLocale('');

        $purchaseCurrency = (new Currency())->setDefaultLocale('');

        $paymentTypeCurrency = (new Currency())->setDefaultLocale('');
        $paymentType = new PaymentType();
        $paymentType->setCurrency($paymentTypeCurrency);

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setCurrency($purchaseCurrency);
        $purchase->setPaymentType($paymentType);

        $this->assertSame(self::DEFAULT_LOCALE, $this->resolver->resolve($purchase));
    }
}
