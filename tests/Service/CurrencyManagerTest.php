<?php

namespace Greendot\EshopBundle\Tests\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

class CurrencyManagerTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private CurrencyRepository&MockObject $currencyRepository;
    private CurrencyManager $currencyManager;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->currencyRepository = $this->createMock(CurrencyRepository::class);

        // No session available (e.g. CLI-like context) so get() falls back to the default currency.
        $this->requestStack->method('getSession')
            ->willThrowException(new SessionNotFoundException());

        $this->currencyManager = new CurrencyManager($this->requestStack, $this->currencyRepository);
    }

    public function testGetForPurchaseReturnsPaymentTypeCurrencyWhenSet(): void
    {
        $eur = new Currency();
        $paymentType = new PaymentType();
        $paymentType->setCurrency($eur);

        $purchase = new Purchase();
        $purchase->setPaymentType($paymentType);

        $this->currencyRepository->expects($this->never())->method('findOneBy');

        $this->assertSame($eur, $this->currencyManager->getForPurchase($purchase));
    }

    public function testGetForPurchaseFallsBackToDefaultCurrencyWhenPaymentTypeHasNoCurrency(): void
    {
        $default = new Currency();

        $paymentType = new PaymentType();
        // no currency set on payment type

        $purchase = new Purchase();
        $purchase->setPaymentType($paymentType);

        $this->currencyRepository->method('findOneBy')
            ->with(['isDefault' => true])
            ->willReturn($default);

        $this->assertSame($default, $this->currencyManager->getForPurchase($purchase));
    }

    public function testGetForPurchaseFallsBackToDefaultCurrencyWhenNoPaymentTypeChosen(): void
    {
        $default = new Currency();

        $purchase = new Purchase();
        // no payment type chosen yet (cart phase)

        $this->currencyRepository->method('findOneBy')
            ->with(['isDefault' => true])
            ->willReturn($default);

        $this->assertSame($default, $this->currencyManager->getForPurchase($purchase));
    }
}
