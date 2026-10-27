<?php

namespace Greendot\EshopBundle\Tests\Stub;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\EventSubscriber\PurchaseStateSubscriber;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\WishlistService;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

/**
 * Shared wiring for tests that drive a purchase through payment transitions:
 * a *real* purchase_flow Workflow with PurchaseStateSubscriber attached, a ManagePurchase
 * whose price calculation yields a fixed total, and a PurchaseUrlGenerator whose endscreen
 * URL is ENDSCREEN_URL. For use in PHPUnit TestCase classes only (relies on createMock()).
 */
trait PurchaseFlowFixtures
{
    protected const ENDSCREEN_URL = 'https://test.example.com/zakaznik/objednavka/999?created=1';

    /**
     * pay_pay / pay_fail between `completed` places. Production config declares purchase_flow
     * with type: 'workflow' (multi-state marking, matching Purchase::$marking being an array),
     * not 'state_machine'.
     */
    private function buildPurchaseWorkflow(
        PaymentActionLogger     $paymentActionLogger,
        ?EntityManagerInterface $entityManager = null,
        ?PaymentRepository      $paymentRepository = null,
    ): WorkflowInterface
    {
        $definition = (new DefinitionBuilder(
            [PWC::S_COMPLETED->value],
            [
                new Transition(PWC::T_PAY_PAY->value, PWC::S_COMPLETED->value, PWC::S_COMPLETED->value),
                new Transition(PWC::T_PAY_FAIL->value, PWC::S_COMPLETED->value, PWC::S_COMPLETED->value),
            ],
        ))->build();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PurchaseStateSubscriber(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(ManageVoucher::class),
            $this->buildManagePurchase($this->createMock(WorkflowInterface::class)),
            new ManageClientDiscount($this->createMock(EntityManagerInterface::class)),
            $this->createMock(DateService::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(WorkflowInterface::class),
            $paymentActionLogger,
            $paymentRepository ?? $this->createMock(PaymentRepository::class),
        ));

        return new Workflow($definition, new MethodMarkingStore(false, 'marking'), $dispatcher, PWC::NAME->value);
    }

    /**
     * ManagePurchase wired to $workflow; with $total, preparePrices() yields that total (in CZK).
     */
    private function buildManagePurchase(?WorkflowInterface $workflow = null, ?Money $total = null): ManagePurchase
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $currencyManager = $this->createMock(CurrencyManager::class);
        $currencyManager->method('get')->willReturn($czk);
        $currencyManager->method('getForPurchase')->willReturn($czk);

        $purchasePriceFactory = $this->createMock(PurchasePriceFactory::class);
        if ($total !== null) {
            $purchasePriceCalc = $this->createMock(PurchasePrice::class);
            $purchasePriceCalc->method('getPrice')->willReturn($total->value);
            $purchasePriceCalc->method('getMoney')->willReturn($total);
            $purchasePriceCalc->method('getPaymentPrice')->willReturn(0.0);
            $purchasePriceCalc->method('getPaymentMoney')->willReturn(new Money(0.0, $total->iso));
            $purchasePriceFactory->method('create')->willReturn($purchasePriceCalc);
        }

        return new ManagePurchase(
            $currencyManager,
            $purchasePriceFactory,
            $this->createMock(ProductVariantPriceFactory::class),
            $this->createMock(PurchaseRepository::class),
            new ManageVies($this->createMock(LoggerInterface::class)),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(ParcelServiceProviderInterface::class),
            $workflow ?? $this->createMock(WorkflowInterface::class),
        );
    }

    private function buildEndscreenUrlGenerator(): PurchaseUrlGenerator
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->with('client_section_order_detail', $this->anything(), $this->anything())
            ->willReturn('https://test.example.com/zakaznik/objednavka/999');

        // Never invoked by buildOrderEndscreenUrl; WishlistService is `readonly` so it can't be mocked.
        $wishlistService = new WishlistService(
            $this->createMock(CurrencyManager::class),
            $this->createMock(PurchasePriceFactory::class),
            $this->createMock(ProductVariantPriceFactory::class),
            $this->createMock(CurrencyRepository::class),
            $this->createMock(\Nzo\UrlEncryptorBundle\Encryptor\Encryptor::class),
            $this->createMock(PurchaseRepository::class),
            'EUR',
        );

        return new PurchaseUrlGenerator($router, $this->createMock(LoginLinkHandlerInterface::class), $wishlistService);
    }
}
