<?php

namespace Greendot\EshopBundle\Tests\Controller\Shop;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Controller\Shop\PurchaseController;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayClient;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

/**
 * Exercises the GoPay return (verifyGoPayOrder) and notification (notifyGoPayOrder) callbacks.
 *
 * Both routes exist in every project using the bundle, also in those without GoPay credentials,
 * so the important part is that an unconfirmed id never resolves one of our purchases.
 */
class PurchaseControllerGoPayTest extends TestCase
{
    use PurchaseFlowFixtures;

    private const HOMEPAGE_URL = 'https://test.example.com/';
    private const GOPAY_ID = '3000000001';

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
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->workflow = $this->buildPurchaseWorkflow($this->paymentActionLogger, $this->entityManager);
        $this->urlGenerator = $this->buildEndscreenUrlGenerator();

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('web_homepage')->willReturn(self::HOMEPAGE_URL);
        $container = new Container();
        $container->set('router', $router);

        $this->controller = new PurchaseController();
        $this->controller->setContainer($container);
    }

    public function testUnconfiguredGoPayNeverLooksUpPaymentAndRedirectsHome(): void
    {
        // Project without GoPay credentials: the token request is rejected
        $this->paymentRepository->expects($this->never())->method('findOneBy');

        $response = $this->verify(['id' => '1'], [
            new MockResponse('{"errors":[{"error_code":202}]}', ['http_code' => 403]),
        ]);

        $this->assertSame(self::HOMEPAGE_URL, $response->headers->get('Location'));
        $this->assertCount(0, $this->paymentActionLogger->calls);
    }

    public function testUnconfiguredGoPayNotificationAnswersBadRequest(): void
    {
        $this->paymentRepository->expects($this->never())->method('findOneBy');

        $response = $this->notify(['id' => '1'], [
            new MockResponse('{"errors":[{"error_code":202}]}', ['http_code' => 403]),
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testNonNumericIdIsRejectedWithoutAnyRequest(): void
    {
        $this->paymentRepository->expects($this->never())->method('findOneBy');

        $response = $this->verify(['id' => '../oauth2/token'], []);

        $this->assertSame(self::HOMEPAGE_URL, $response->headers->get('Location'));
    }

    public function testPaymentOfAnotherOrderIsNotResolved(): void
    {
        // e.g. a GP webpay payment sharing the externalId, or a GoPay payment of a different order
        $purchase = $this->createCompletedPurchase(14048);
        $this->paymentRepository->method('findOneBy')->willReturn($this->createPayment($purchase));

        $response = $this->verify(['id' => self::GOPAY_ID], $this->goPayResponses('PAID', '99999'));

        $this->assertSame(self::HOMEPAGE_URL, $response->headers->get('Location'));
        $this->assertFalse($purchase->isPaid());
        $this->assertCount(0, $this->paymentActionLogger->calls);
    }

    public function testPaidPaymentMarksPurchasePaidAndRedirectsToEndscreen(): void
    {
        $purchase = $this->createCompletedPurchase(14048);
        $this->paymentRepository->method('findOneBy')
            ->with(['externalId' => self::GOPAY_ID])
            ->willReturn($this->createPayment($purchase));

        $response = $this->verify(['id' => self::GOPAY_ID], $this->goPayResponses('PAID', '14048'));

        $this->assertTrue($purchase->isPaid());
        $this->assertSame(self::ENDSCREEN_URL, $response->headers->get('Location'));
        $this->assertSame('gopay_return', $this->paymentActionLogger->calls[0][1]);
    }

    public function testCanceledPaymentNotificationFailsPurchase(): void
    {
        $purchase = $this->createCompletedPurchase(14048);
        $this->paymentRepository->method('findOneBy')->willReturn($this->createPayment($purchase));

        $response = $this->notify(['id' => self::GOPAY_ID], $this->goPayResponses('CANCELED', '14048'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($purchase->isPaid());
        $this->assertTrue($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame('gopay_notify', $this->paymentActionLogger->calls[0][1]);
    }

    public function testPendingPaymentAppliesNoTransition(): void
    {
        $purchase = $this->createCompletedPurchase(14048);
        $this->paymentRepository->method('findOneBy')->willReturn($this->createPayment($purchase));

        $response = $this->notify(['id' => self::GOPAY_ID], $this->goPayResponses('PAYMENT_METHOD_CHOSEN', '14048'));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($purchase->isPaid());
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertCount(1, $this->paymentActionLogger->calls, 'only the gopay_notify entry, no state change');
    }

    private function verify(array $query, array $responses): Response
    {
        $request = new Request($query);

        return $this->controller->verifyGoPayOrder(
            $this->buildGoPay($request, $responses),
            $this->paymentRepository,
            $this->entityManager,
            $this->workflow,
            $this->urlGenerator,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
            $request,
        );
    }

    private function notify(array $query, array $responses): Response
    {
        $request = new Request($query);

        return $this->controller->notifyGoPayOrder(
            $this->buildGoPay($request, $responses),
            $this->paymentRepository,
            $this->entityManager,
            $this->workflow,
            $this->createMock(LoggerInterface::class),
            $this->paymentActionLogger,
            $request,
        );
    }

    private function buildGoPay(Request $request, array $responses): GoPay
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $httpClient = new MockHttpClient(function () use (&$responses) {
            return array_shift($responses) ?? throw new \LogicException('Unexpected GoPay request');
        });

        return new GoPay(
            new GoPayClient($httpClient, '', '', '0', GoPayClient::SANDBOX_URL),
            $this->createMock(UrlGeneratorInterface::class),
            $this->entityManager,
            $this->createMock(LoggerInterface::class),
            $this->buildManagePurchase(),
            $this->createMock(CurrencyManager::class),
            $this->paymentActionLogger,
            $requestStack,
        );
    }

    private function goPayResponses(string $state, string $orderNumber): array
    {
        return [
            new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800])),
            new MockResponse(json_encode(['id' => (int)self::GOPAY_ID, 'state' => $state, 'order_number' => $orderNumber])),
        ];
    }

    private function createCompletedPurchase(int $orderNumber): Purchase
    {
        $purchase = new Purchase();
        $purchase->setClient((new Client())->setIsAnonymous(false));
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
        $purchase->assignOrderNumber($orderNumber);

        return $purchase;
    }

    private function createPayment(Purchase $purchase): Payment
    {
        return (new Payment())
            ->setPurchase($purchase)
            ->setExternalId(self::GOPAY_ID)
            ->setAmount(150.0);
    }
}
