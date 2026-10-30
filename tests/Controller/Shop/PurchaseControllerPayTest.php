<?php

namespace Greendot\EshopBundle\Tests\Controller\Shop;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Greendot\EshopBundle\Controller\Shop\PurchaseController;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentGatewayProvider;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\FakeGpWebpayGateway;
use Greendot\EshopBundle\Tests\Stub\FakeCallbackGateway;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

/**
 * Exercises PurchaseController::pay() — the "pay again" link that sends the client to the purchase's payment gateway.
 *
 * An order fully covered by vouchers has a total of 0, which GP webpay rejects
 * ("Incorrect content of field, AMOUNT"), so pay() must settle it as paid instead
 * of ever asking the gateway for a link.
 */
class PurchaseControllerPayTest extends TestCase
{
    use PurchaseFlowFixtures;

    private MockObject $entityManager;
    private WorkflowInterface $workflow;
    private RecordingPaymentActionLogger $paymentActionLogger;
    private PurchaseUrlGenerator $urlGenerator;
    private PurchaseController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->workflow = $this->buildPurchaseWorkflow($this->paymentActionLogger, $this->entityManager);
        $this->urlGenerator = $this->buildEndscreenUrlGenerator();

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $container = new Container();
        $container->set('security.authorization_checker', $authorizationChecker);

        $this->controller = new PurchaseController();
        $this->controller->setContainer($container);
    }

    public function testZeroTotalPurchaseIsSettledWithoutGatewayAndRedirectsToEndscreen(): void
    {
        $purchase = $this->createCompletedPurchase();

        // PurchaseStateSubscriber::onTrackFinished() flushes as well, on top of pay()'s own flush
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        // No payLinkResult configured: FakeGpWebpayGateway::getPayLink() would throw
        // LogicException if called, proving the gateway is never asked for an AMOUNT=0 link.
        $response = $this->pay($purchase, new FakeGpWebpayGateway(), new Money(0.0, 'CZK'));

        $this->assertTrue($purchase->isPaid());
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame(self::ENDSCREEN_URL, $response->getTargetUrl());

        $this->assertCount(1, $this->paymentActionLogger->calls);
        [, $name, , , $data] = $this->paymentActionLogger->calls[0];
        $this->assertSame('state_paid', $name);
        $this->assertSame('pay_endpoint_zero_total', $data['source']);
    }

    public function testPositiveTotalPurchaseStillRedirectsToGateway(): void
    {
        $purchase = $this->createCompletedPurchase();

        $response = $this->pay(
            $purchase,
            new FakeGpWebpayGateway(payLinkResult: 'https://gateway.example.com/pay'),
            new Money(150.0, 'CZK'),
        );

        $this->assertFalse($purchase->isPaid());
        $this->assertSame('https://gateway.example.com/pay', $response->getTargetUrl());
        $this->assertCount(0, $this->paymentActionLogger->calls);
    }

    public function testPurchaseWithoutGatewayUsesConfiguredDefaultGateway(): void
    {
        // e.g. the "pay by card" link of a bank transfer order in a project that switched to GoPay
        $purchase = $this->createCompletedPurchase();

        $response = $this->controller->pay(
            $purchase,
            new PaymentGatewayProvider(
                [new FakeGpWebpayGateway(), new FakeGoPayLinkGateway('https://gopay.example.com/pay')],
                PaymentTechnicalAction::GOPAY->value,
            ),
            $this->urlGenerator,
            $this->workflow,
            $this->entityManager,
            $this->createMock(LoggerInterface::class),
            $this->buildManagePurchase($this->workflow, new Money(150.0, 'CZK')),
        );

        $this->assertSame('https://gopay.example.com/pay', $response->getTargetUrl());
    }

    private function pay(Purchase $purchase, FakeGpWebpayGateway $gateway, Money $total): RedirectResponse
    {
        // The purchase has no payment type, so the provider falls back to its default (GP webpay) gateway
        return $this->controller->pay(
            $purchase,
            new PaymentGatewayProvider([$gateway]),
            $this->urlGenerator,
            $this->workflow,
            $this->entityManager,
            $this->createMock(LoggerInterface::class),
            $this->buildManagePurchase($this->workflow, $total),
        );
    }

    private function createCompletedPurchase(): Purchase
    {
        $purchase = new Purchase();
        $purchase->setClient((new Client())->setIsAnonymous(false));
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);

        return $purchase;
    }
}

class FakeGoPayLinkGateway extends FakeCallbackGateway
{
    public function __construct(private readonly string $payLink)
    {
        parent::__construct();
    }

    public function getPayLink(Purchase $purchase): string
    {
        return $this->payLink;
    }
}
