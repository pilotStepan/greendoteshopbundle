<?php

namespace Greendot\EshopBundle\Tests\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

class CurrencyManagerTest extends TestCase
{
    public function testGetForPurchaseReturnsPurchaseCurrencyWhenSet(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $manager = new CurrencyManager($requestStack, $currencyRepository);

        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);
        $purchase = (new Purchase())->setCurrency($eur);

        // Neither the session nor the currency repository should be touched: the purchase
        // already knows its own currency.
        $requestStack->expects($this->never())->method('getSession');
        $currencyRepository->expects($this->never())->method('findOneBy');

        $this->assertSame($eur, $manager->getForPurchase($purchase));
    }

    public function testGetForPurchaseFallsBackToDefaultWithoutSessionOrPurchaseCurrency(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willThrowException(new SessionNotFoundException());

        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->with(['isDefault' => true])->willReturn($czk);

        $manager = new CurrencyManager($requestStack, $currencyRepository);
        $purchase = new Purchase();

        $this->assertSame($czk, $manager->getForPurchase($purchase));
    }

    public function testGetLocaleReturnsEmptyStringWithoutSessionInsteadOfThrowing(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willThrowException(new SessionNotFoundException());

        $manager = new CurrencyManager($requestStack, $this->createMock(CurrencyRepository::class));

        $this->assertSame('', $manager->getLocale());
    }

}
