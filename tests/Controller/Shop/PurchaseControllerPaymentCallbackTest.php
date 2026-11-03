<?php

namespace Greendot\EshopBundle\Tests\Controller\Shop;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Controller\Shop\PurchaseController;
use Greendot\EshopBundle\Enum\PaymentOutcome;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\Payment\PaymentCallbackHandler;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentResult;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentGatewayProvider;
use Greendot\EshopBundle\Service\PaymentGateway\UnresolvedPaymentException;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\FakeCallbackGateway;
use Greendot\EshopBundle\Tests\Stub\FakeGpWebpayGateway;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

/**
 * Exercises the shared gateway callback routes (paymentReturn / paymentNotify).
 * What happens to the purchase is covered by PaymentCallbackHandlerTest.
 */
class PurchaseControllerPaymentCallbackTest extends TestCase
{
    use PurchaseFlowFixtures;

    private const HOMEPAGE_URL = 'https://test.example.com/';

    private PaymentCallbackHandler $handler;
    private PurchaseController $controller;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $paymentActionLogger = new RecordingPaymentActionLogger();

        $this->handler = new PaymentCallbackHandler(
            $entityManager,
            $this->buildPurchaseWorkflow($paymentActionLogger, $entityManager),
            new LockFactory(new InMemoryStore()),
            $paymentActionLogger,
            $this->createMock(LoggerInterface::class),
        );

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('web_homepage')->willReturn(self::HOMEPAGE_URL);
        $container = new Container();
        $container->set('router', $router);

        $this->controller = new PurchaseController();
        $this->controller->setContainer($container);
    }

    public function testGatewayWithoutSharedCallbacksIsNotFound(): void
    {
        // GP webpay keeps its own /order/verify route
        $provider = new PaymentGatewayProvider([new FakeGpWebpayGateway()]);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->paymentNotify(PaymentTechnicalAction::GLOBAL_PAYMENTS, new Request(), $provider, $this->handler);
    }

    public function testUnregisteredGatewayIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller->paymentReturn(
            PaymentTechnicalAction::GOPAY,
            new Request(),
            new PaymentGatewayProvider([]),
            $this->handler,
            $this->buildEndscreenUrlGenerator(),
        );
    }

    public function testReturnRedirectsToEndscreen(): void
    {
        $response = $this->return(new PaymentResult($this->createPayment(), PaymentOutcome::PAID, 15000, 'CZK'));

        $this->assertSame(self::ENDSCREEN_URL, $response->headers->get('Location'));
    }

    public function testUnresolvedReturnRedirectsHome(): void
    {
        $response = $this->return(new UnresolvedPaymentException('foreign payment'));

        $this->assertSame(self::HOMEPAGE_URL, $response->headers->get('Location'));
    }

    public function testResolvedNotificationIsAcknowledged(): void
    {
        $response = $this->notify(new PaymentResult($this->createPayment(), PaymentOutcome::PENDING));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testUnresolvedNotificationAsksForRetry(): void
    {
        $response = $this->notify(new UnresolvedPaymentException('foreign payment'));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function return(PaymentResult|\Throwable $result): Response
    {
        return $this->controller->paymentReturn(
            PaymentTechnicalAction::GOPAY,
            new Request(['id' => '3000000001']),
            new PaymentGatewayProvider([new FakeCallbackGateway($result)]),
            $this->handler,
            $this->buildEndscreenUrlGenerator(),
        );
    }

    private function notify(PaymentResult|\Throwable $result): Response
    {
        return $this->controller->paymentNotify(
            PaymentTechnicalAction::GOPAY,
            new Request(['id' => '3000000001']),
            new PaymentGatewayProvider([new FakeCallbackGateway($result)]),
            $this->handler,
        );
    }

    private function createPayment(): Payment
    {
        $purchase = new Purchase();
        $purchase->setClient((new Client())->setIsAnonymous(false));
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
        $purchase->assignOrderNumber(14048);

        $payment = (new Payment())->setExternalId('3000000001')->setAmount(150.0);
        $purchase->addPayment($payment);

        return $payment;
    }
}
