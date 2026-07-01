<?php

namespace Greendot\EshopBundle\Tests\Controller\Shop;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Granam\GpWebPay\CardPayResponse;
use Granam\GpWebPay\SettingsInterface;
use Granam\GpWebPay\DigestSignerInterface;
use Granam\GpWebPay\Codes\PrCodes;
use Granam\GpWebPay\Exceptions\GpWebPayErrorResponse;
use Greendot\EshopBundle\Controller\Shop\PurchaseController;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\EventSubscriber\PurchaseStateSubscriber;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Greendot\EshopBundle\Service\WishlistService;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\FakeGpWebpayGateway;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Exercises PurchaseController::verifyOrder() — the callback GPWebpay redirects
 * the client's browser to after they finish paying on the gateway's own page.
 *
 * GPWebpay (our wrapper) is a `readonly` class, so it can't be doubled with
 * createMock(); real verifyLink() also needs on-disk RSA key material and
 * performs real digest signing. FakeGpWebpayGateway (a readonly subclass)
 * replaces it so these tests can say "the gateway told us PRCODE=X/SRCODE=Y"
 * (or "the gateway declined the payment") without any of that, and instead
 * assert on OUR logic:
 *   - the purchase ends up in the correct state (paid / failed), verified
 *     through a *real* Workflow + PurchaseStateSubscriber, not mocked calls
 *   - the client is redirected to the correct endscreen URL
 *
 * No Symfony container/kernel is booted: verifyOrder() only calls the
 * container-free AbstractController helpers redirect() and
 * createNotFoundException(), so the controller can be exercised directly.
 */
class PurchaseControllerVerifyOrderTest extends TestCase
{
    private MockObject $entityManager;
    private MockObject $paymentRepository;
    private WorkflowInterface $workflow;
    private RecordingPaymentActionLogger $paymentActionLogger;
    private PurchaseUrlGenerator $urlGenerator;
    private PurchaseController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $this->entityManager->method('getRepository')->willReturnMap([
            [Payment::class, $this->paymentRepository],
            [PaymentType::class, $paymentTypeRepository],
        ]);
        $this->paymentActionLogger = new RecordingPaymentActionLogger();

        $definition = (new DefinitionBuilder(
            [PWC::S_COMPLETED->value],
            [
                new Transition(PWC::T_PAY_PAY->value, PWC::S_COMPLETED->value, PWC::S_COMPLETED->value),
                new Transition(PWC::T_PAY_FAIL->value, PWC::S_COMPLETED->value, PWC::S_COMPLETED->value),
            ],
        ))->build();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->buildPurchaseStateSubscriber());

        // Production config (config/packages/workflow.yaml) declares purchase_flow
        // with type: 'workflow' (multi-state marking), not 'state_machine'.
        $this->workflow = new Workflow($definition, new MethodMarkingStore(false, 'marking'), $dispatcher, PWC::NAME->value);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->with('client_section_order_detail', $this->anything(), $this->anything())
            ->willReturn('https://test.example.com/zakaznik/objednavka/999');

        $this->urlGenerator = new PurchaseUrlGenerator(
            $router,
            $this->createMock(LoginLinkHandlerInterface::class),
            $this->buildWishlistService(),
        );

        $this->controller = new PurchaseController();
    }

    public function testSuccessfulPaymentMarksPurchasePaidAndRedirectsToEndscreen(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = (new Payment())->setPurchase($purchase);

        $this->paymentRepository->method('find')->with('42')->willReturn($payment);

        $gateway = new FakeGpWebpayGateway(verifyResult: $this->buildCardPayResponse(orderNumber: 42, prCode: PrCodes::OK_CODE, srCode: 0));

        $response = $this->controller->verifyOrder(
            $this->buildRequest(['ORDERNUMBER' => '42']),
            $gateway,
            $this->entityManager,
            $this->workflow,
            $this->urlGenerator,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
        );

        $this->assertTrue($purchase->isPaid(), 'Purchase must be marked paid on a successful gateway response');
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://test.example.com/zakaznik/objednavka/999?created=1', $response->getTargetUrl());
    }

    public function testDeclinedPaymentMarksPurchaseFailedAndRedirectsToEndscreen(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = (new Payment())->setPurchase($purchase);

        $this->paymentRepository->method('find')->with('42')->willReturn($payment);

        $gateway = new FakeGpWebpayGateway(
            verifyResult: new GpWebPayErrorResponse(PrCodes::THE_CARDHOLDER_CANCELED_THE_PAYMENT, 0, 'Declined by customer'),
        );

        $response = $this->controller->verifyOrder(
            $this->buildRequest(['ORDERNUMBER' => '42']),
            $gateway,
            $this->entityManager,
            $this->workflow,
            $this->urlGenerator,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
        );

        $this->assertFalse($purchase->isPaid(), 'Purchase must not be marked paid when the gateway declines');
        $this->assertTrue($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://test.example.com/zakaznik/objednavka/999?created=1', $response->getTargetUrl());
    }

    public function testGatewayCommunicationFailureRedirectsToEndscreenWithoutChangingPaymentState(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = (new Payment())->setPurchase($purchase);

        $this->paymentRepository->method('find')->with('42')->willReturn($payment);

        $gateway = new FakeGpWebpayGateway(verifyResult: new \RuntimeException('Gateway timed out'));

        $response = $this->controller->verifyOrder(
            $this->buildRequest(['ORDERNUMBER' => '42']),
            $gateway,
            $this->entityManager,
            $this->workflow,
            $this->urlGenerator,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
        );

        // We genuinely don't know the outcome here (gateway didn't answer), so
        // we must NOT guess a state — the purchase is left untouched for later
        // reconciliation, but the client still lands on the endscreen.
        $this->assertFalse($purchase->isPaid());
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://test.example.com/zakaznik/objednavka/999?created=1', $response->getTargetUrl());
    }

    public function testAlreadyPaidPurchaseSkipsGatewayAndRedirectsToEndscreen(): void
    {
        $purchase = $this->createCompletedPurchase();
        $purchase->assignWorkflowFlag(PWC::F_PAYMENT_SUCCESS->value);
        $payment = (new Payment())->setPurchase($purchase);

        $this->paymentRepository->method('find')->with('42')->willReturn($payment);

        // No verifyResult configured: FakeGpWebpayGateway::verifyLink() would
        // throw LogicException if called, proving the controller short-circuits
        // before ever talking to the gateway for an already-paid purchase.
        $gateway = new FakeGpWebpayGateway();

        $response = $this->controller->verifyOrder(
            $this->buildRequest(['ORDERNUMBER' => '42']),
            $gateway,
            $this->entityManager,
            $this->workflow,
            $this->urlGenerator,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
        );

        $this->assertTrue($purchase->isPaid());
        $this->assertSame('https://test.example.com/zakaznik/objednavka/999?created=1', $response->getTargetUrl());
    }

    private function createCompletedPurchase(): Purchase
    {
        $client = (new Client())->setIsAnonymous(false);

        $purchase = new Purchase();
        $purchase->setClient($client);
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);

        return $purchase;
    }

    private function buildRequest(array $query): Request
    {
        return new Request($query);
    }

    private function buildCardPayResponse(int $orderNumber, int $prCode, int $srCode): CardPayResponse
    {
        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('getMerchantNumber')->willReturn('2200000458');

        $digestSigner = $this->createMock(DigestSignerInterface::class);
        $digestSigner->method('verifySignedDigest')->willReturn(true);

        return new CardPayResponse(
            $settings,
            $digestSigner,
            'FINALIZE_ORDER',
            $orderNumber,
            $prCode,
            $srCode,
            'fake-digest',
            'fake-digest1',
        );
    }

    private function buildPurchaseStateSubscriber(): PurchaseStateSubscriber
    {
        return new PurchaseStateSubscriber(
            $this->entityManager,
            $this->createMock(ManageVoucher::class),
            $this->buildManagePurchase(),
            new ManageClientDiscount($this->createMock(EntityManagerInterface::class)),
            $this->createMock(DateService::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(WorkflowInterface::class),
            $this->paymentActionLogger,
            $this->paymentRepository,
        );
    }

    private function buildWishlistService(): WishlistService
    {
        // Never actually invoked (buildOrderEndscreenUrl doesn't touch wishlists) —
        // WishlistService is `readonly` so it can't be mocked; a real instance with
        // mocked dependencies is the only way to satisfy PurchaseUrlGenerator's
        // constructor.
        return new WishlistService(
            $this->createMock(CurrencyManager::class),
            $this->createMock(PurchasePriceFactory::class),
            $this->createMock(ProductVariantPriceFactory::class),
            $this->createMock(\Greendot\EshopBundle\Repository\Project\CurrencyRepository::class),
            $this->createMock(\Nzo\UrlEncryptorBundle\Encryptor\Encryptor::class),
            $this->createMock(PurchaseRepository::class),
            'EUR',
        );
    }

    private function buildManagePurchase(): ManagePurchase
    {
        return new ManagePurchase(
            $this->createMock(CurrencyManager::class),
            $this->createMock(PurchasePriceFactory::class),
            $this->createMock(ProductVariantPriceFactory::class),
            $this->createMock(PurchaseRepository::class),
            new ManageVies($this->createMock(LoggerInterface::class)),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(ParcelServiceProviderInterface::class),
            $this->createMock(WorkflowInterface::class),
        );
    }
}
