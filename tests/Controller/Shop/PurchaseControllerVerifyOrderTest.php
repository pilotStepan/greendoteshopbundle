<?php

namespace Greendot\EshopBundle\Tests\Controller\Shop;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Workflow\WorkflowInterface;
use Granam\GpWebPay\CardPayResponse;
use Granam\GpWebPay\SettingsInterface;
use Granam\GpWebPay\DigestSignerInterface;
use Granam\GpWebPay\Codes\PrCodes;
use Granam\GpWebPay\Exceptions\GpWebPayErrorResponse;
use Greendot\EshopBundle\Controller\Shop\PurchaseController;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\FakeGpWebpayGateway;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

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
    use PurchaseFlowFixtures;

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
        $this->workflow = $this->buildPurchaseWorkflow($this->paymentActionLogger, $this->entityManager, $this->paymentRepository);
        $this->urlGenerator = $this->buildEndscreenUrlGenerator();

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

    public function testSuccessfulPaymentLogsTheAmountAndCurrencyThePaymentWasRedirectedWith(): void
    {
        $purchase = $this->createCompletedPurchase();
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);
        $payment = (new Payment())->setPurchase($purchase)->setAmount(1234.0)->setCurrency($czk);

        $this->paymentRepository->method('find')->with('42')->willReturn($payment);

        $gateway = new FakeGpWebpayGateway(verifyResult: $this->buildCardPayResponse(orderNumber: 42, prCode: PrCodes::OK_CODE, srCode: 0));

        $this->controller->verifyOrder(
            $this->buildRequest(['ORDERNUMBER' => '42']),
            $gateway,
            $this->entityManager,
            $this->workflow,
            $this->urlGenerator,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
        );

        $statePaidCall = null;
        foreach ($this->paymentActionLogger->calls as $call) {
            if ($call[1] === 'state_paid') {
                $statePaidCall = $call;
            }
        }
        $this->assertNotNull($statePaidCall, 'Expected a state_paid PaymentAction to be logged');

        [, , , , $data] = $statePaidCall;
        $this->assertSame(1234.0, $data['amount']);
        $this->assertSame('CZK', $data['currency']);
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

}
