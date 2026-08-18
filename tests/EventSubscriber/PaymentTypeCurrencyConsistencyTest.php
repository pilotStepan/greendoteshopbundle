<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\EventSubscriber\PaymentTypeEventListener;
use Greendot\EshopBundle\EventSubscriber\TransportationEventListener;

/**
 * Regression coverage for M2: PaymentTypeEventListener and
 * TransportationEventListener price cart-scoped services onto the same
 * order and must therefore ask CurrencyManager for the same thing when a
 * cart exists - getForPurchase($cart), not the session/default currency
 * via get(). ServiceCalculationUtils/PurchasePriceFactory are `readonly`
 * (cannot be doubled), so real instances are used with their own
 * collaborators mocked to the "no handling price row -> free/0.0" path,
 * which lets calculateServicePrice() run without touching PriceUtils.
 */
class PaymentTypeCurrencyConsistencyTest extends TestCase
{
    public function testPaymentTypeListenerAsksCurrencyManagerForCartCurrencyWhenCartExists(): void
    {
        $eur = new Currency();
        $existingPaymentType = new PaymentType();
        $existingPaymentType->setCurrency($eur);

        $cart = new Purchase();
        $cart->setPaymentType($existingPaymentType);

        $currencyManager = $this->createMock(CurrencyManager::class);
        $currencyManager->expects($this->once())->method('getForPurchase')->with($cart)->willReturn($eur);
        $currencyManager->expects($this->never())->method('get');

        $listener = new PaymentTypeEventListener(
            $this->buildEntityManagerReturningCart($cart),
            $this->buildPurchasePriceFactory(),
            $this->buildServiceCalculationUtils(),
            $currencyManager,
        );

        $listener->postLoad(new PaymentType());
    }

    public function testPaymentTypeListenerFallsBackToSessionCurrencyWhenNoCart(): void
    {
        $default = new Currency();

        $currencyManager = $this->createMock(CurrencyManager::class);
        $currencyManager->expects($this->never())->method('getForPurchase');
        $currencyManager->expects($this->once())->method('get')->willReturn($default);

        $listener = new PaymentTypeEventListener(
            $this->buildEntityManagerReturningCart(null),
            $this->buildPurchasePriceFactory(),
            $this->buildServiceCalculationUtils(),
            $currencyManager,
        );

        $listener->postLoad(new PaymentType());
    }

    /**
     * The actual M2 regression: for the same cart, both listeners must ask
     * CurrencyManager for the same thing. Before the fix, PaymentTypeEventListener
     * called get() while TransportationEventListener called getForPurchase($cart) -
     * a EUR cart could end up pricing transport in EUR and payment in the
     * session/default currency.
     */
    public function testPaymentTypeAndTransportationListenersBothCallGetForPurchaseForTheSameCart(): void
    {
        $eur = new Currency();
        $existingPaymentType = new PaymentType();
        $existingPaymentType->setCurrency($eur);

        $cart = new Purchase();
        $cart->setPaymentType($existingPaymentType);

        $currencyManager = $this->createMock(CurrencyManager::class);
        $currencyManager->expects($this->exactly(2))->method('getForPurchase')->with($cart)->willReturn($eur);
        $currencyManager->expects($this->never())->method('get');

        $paymentTypeListener = new PaymentTypeEventListener(
            $this->buildEntityManagerReturningCart($cart),
            $this->buildPurchasePriceFactory(),
            $this->buildServiceCalculationUtils(),
            $currencyManager,
        );
        $paymentTypeListener->postLoad(new PaymentType());

        $transportationListener = new TransportationEventListener(
            $this->buildEntityManagerReturningCart($cart),
            $this->buildPurchasePriceFactory(),
            $this->buildServiceCalculationUtils(),
            $currencyManager,
        );
        $transportationListener->postLoad(new Transportation());
    }

    private function buildEntityManagerReturningCart(?Purchase $cart): EntityManagerInterface&MockObject
    {
        $purchaseRepository = $this->createMock(PurchaseRepository::class);
        $purchaseRepository->method('findOneBySession')->willReturn($cart);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Purchase::class)->willReturn($purchaseRepository);

        return $entityManager;
    }

    private function buildServiceCalculationUtils(): ServiceCalculationUtils
    {
        // No HandlingPrice row -> calculateServicePrice()/getFreeFromPrice() take
        // the "free"/null shortcut and never touch PriceUtils.
        $handlingPriceRepository = $this->createMock(HandlingPriceRepository::class);
        $handlingPriceRepository->method('getByDate')->willReturn(null);

        return new ServiceCalculationUtils(
            $handlingPriceRepository,
            $this->createMock(PriceUtils::class),
        );
    }

    private function buildPurchasePriceFactory(): PurchasePriceFactory&MockObject
    {
        $factory = $this->createMock(PurchasePriceFactory::class);
        $purchasePrice = $this->createMock(\Greendot\EshopBundle\Service\Price\PurchasePrice::class);
        $purchasePrice->method('getPaymentPrice')->willReturn(0.0);
        $purchasePrice->method('getTransportationPrice')->willReturn(0.0);
        $purchasePrice->method('getPrice')->willReturn(0.0);
        $factory->method('create')->willReturn($purchasePrice);

        return $factory;
    }
}
