<?php

namespace Greendot\EshopBundle\Tests\Service\Payment;

use RuntimeException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Enum\PaymentOutcome;
use Greendot\EshopBundle\Enum\PaymentCallbackSource;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\Payment\PaymentCallbackHandler;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentResult;
use Greendot\EshopBundle\Service\PaymentGateway\UnresolvedPaymentException;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\FakeCallbackGateway;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

class PaymentCallbackHandlerTest extends TestCase
{
    use PurchaseFlowFixtures;

    private MockObject $entityManager;
    private WorkflowInterface $workflow;
    private RecordingPaymentActionLogger $paymentActionLogger;
    private PaymentCallbackHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->workflow = $this->buildPurchaseWorkflow($this->paymentActionLogger, $this->entityManager);

        $this->handler = new PaymentCallbackHandler(
            $this->entityManager,
            $this->workflow,
            new LockFactory(new InMemoryStore()),
            $this->paymentActionLogger,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testPaidCallbackMarksPurchasePaid(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = $this->addPayment($purchase, 1);

        $this->entityManager->expects($this->once())->method('refresh')->with($purchase);

        $result = $this->handle(new PaymentResult($payment, PaymentOutcome::PAID, 15000, 'CZK'));

        $this->assertSame($purchase, $result);
        $this->assertTrue($purchase->isPaid());
        $this->assertSame('gopay_return', $this->paymentActionLogger->calls[0][1]);
        $this->assertSame('client', $this->paymentActionLogger->calls[0][2]);
        $this->assertSame('state_paid', $this->paymentActionLogger->calls[1][1]);
    }

    public function testPurchasePaidByConcurrentCallbackIsNotPaidAgain(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = $this->addPayment($purchase, 1);

        // The notification won the lock and paid the purchase - refresh() reveals it to the return request
        $this->entityManager->method('refresh')->willReturnCallback(
            fn (Purchase $p) => $p->assignWorkflowFlag(PWC::F_PAYMENT_SUCCESS->value)
        );

        $result = $this->handle(new PaymentResult($payment, PaymentOutcome::PAID, 15000, 'CZK'));

        $this->assertSame($purchase, $result);
        $this->assertCount(1, $this->paymentActionLogger->calls, 'only gopay_return, no second state_paid');
    }

    public function testAmountMismatchLeavesPurchaseUnpaid(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = $this->addPayment($purchase, 1);

        $result = $this->handle(new PaymentResult($payment, PaymentOutcome::PAID, 10000, 'CZK'));

        $this->assertSame($purchase, $result);
        $this->assertFalse($purchase->isPaid());
        $this->assertCount(1, $this->paymentActionLogger->calls);
    }

    public function testCurrencyMismatchLeavesPurchaseUnpaid(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = $this->addPayment($purchase, 1);

        $this->handle(new PaymentResult($payment, PaymentOutcome::PAID, 15000, 'EUR'));

        $this->assertFalse($purchase->isPaid());
    }

    public function testFailureOfLatestAttemptFailsPurchase(): void
    {
        $purchase = $this->createCompletedPurchase();
        $this->addPayment($purchase, 1);
        $latest = $this->addPayment($purchase, 2);

        $result = $this->handle(new PaymentResult($latest, PaymentOutcome::FAILED), PaymentCallbackSource::NOTIFY);

        $this->assertSame($purchase, $result);
        $this->assertTrue($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame('gopay_notify', $this->paymentActionLogger->calls[0][1]);
        $this->assertSame('system', $this->paymentActionLogger->calls[0][2]);
        $this->assertSame('state_failed', $this->paymentActionLogger->calls[1][1]);
    }

    public function testFailureOfOlderAttemptIsIgnored(): void
    {
        // e.g. the abandoned first attempt times out while the client is paying the second one
        $purchase = $this->createCompletedPurchase();
        $older = $this->addPayment($purchase, 1);
        $this->addPayment($purchase, 2);

        $result = $this->handle(new PaymentResult($older, PaymentOutcome::FAILED), PaymentCallbackSource::NOTIFY);

        $this->assertSame($purchase, $result);
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertCount(1, $this->paymentActionLogger->calls);
    }

    public function testPendingAppliesNoTransition(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = $this->addPayment($purchase, 1);

        $result = $this->handle(new PaymentResult($payment, PaymentOutcome::PENDING), PaymentCallbackSource::NOTIFY);

        $this->assertSame($purchase, $result);
        $this->assertFalse($purchase->isPaid());
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertCount(1, $this->paymentActionLogger->calls);
    }

    public function testUnresolvedCallbackReturnsNull(): void
    {
        $this->entityManager->expects($this->never())->method('flush');

        $this->assertNull($this->handle(new UnresolvedPaymentException('foreign payment')));
        $this->assertNull($this->handle(new UnresolvedPaymentException('foreign payment'), PaymentCallbackSource::NOTIFY));
        $this->assertCount(0, $this->paymentActionLogger->calls);
    }

    public function testUnreachableGatewayReturnsNull(): void
    {
        $this->assertNull($this->handle(new RuntimeException('GoPay is down'), PaymentCallbackSource::NOTIFY));
        $this->assertNull($this->handle(new RuntimeException('GoPay is down')));
    }

    public function testProcessingErrorKeepsPurchaseForReturnButLetsNotificationRetry(): void
    {
        $purchase = $this->createCompletedPurchase();
        $payment = $this->addPayment($purchase, 1);
        $this->entityManager->method('flush')->willThrowException(new RuntimeException('DB is gone'));

        $pending = new PaymentResult($payment, PaymentOutcome::PENDING);

        $this->assertSame($purchase, $this->handle($pending));
        $this->assertNull($this->handle($pending, PaymentCallbackSource::NOTIFY));
    }

    private function handle(PaymentResult|\Throwable $result, PaymentCallbackSource $source = PaymentCallbackSource::RETURN): ?Purchase
    {
        return $this->handler->handle(new FakeCallbackGateway($result), new Request(['id' => '3000000001']), $source);
    }

    private function createCompletedPurchase(): Purchase
    {
        $purchase = new Purchase();
        (new \ReflectionProperty($purchase, 'id'))->setValue($purchase, 14284);
        $purchase->setClient((new Client())->setIsAnonymous(false));
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
        $purchase->assignOrderNumber(14048);

        return $purchase;
    }

    private function addPayment(Purchase $purchase, int $id): Payment
    {
        $payment = (new Payment())
            ->setExternalId((string)(3000000000 + $id))
            ->setAmount(150.0)
            ->setCurrency((new Currency())->setName('CZK'));
        (new \ReflectionProperty($payment, 'id'))->setValue($payment, $id);
        $purchase->addPayment($payment);

        return $payment;
    }
}
