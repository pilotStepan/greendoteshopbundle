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

        $currency = (new Currency())->setDefaultLocale('de'); // should be ignored
        $paymentType = new PaymentType();
        $paymentType->setCurrency($currency);

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setPaymentType($paymentType);

        $this->assertSame('sk', $this->resolver->resolve($purchase));
    }

    public function testResolveFallsBackToPaymentTypeCurrencyDefaultLocaleWhenClientHasNone(): void
    {
        $client = new Client(); // no locale set

        $currency = (new Currency())->setDefaultLocale('sk');
        $paymentType = new PaymentType();
        $paymentType->setCurrency($currency);

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setPaymentType($paymentType);

        $this->assertSame('sk', $this->resolver->resolve($purchase));
    }

    public function testResolveFallsBackToDefaultLocaleWhenNothingElseAvailable(): void
    {
        $purchase = new Purchase(); // no client, no payment type

        $this->assertSame(self::DEFAULT_LOCALE, $this->resolver->resolve($purchase));
    }
}
