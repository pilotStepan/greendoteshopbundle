<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Transition;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Greendot\EshopBundle\EventSubscriber\PurchaseStateSubscriber;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

class PurchaseStateSubscriberPaymentLogTest extends TestCase
{
    private RecordingPaymentActionLogger $paymentActionLogger;
    private MockObject $paymentRepository;
    private PurchaseStateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        $this->subscriber = new PurchaseStateSubscriber(
            $this->createMock(EntityManagerInterface::class),
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

    public function testOnPaymentLogsStatePaidWithContext(): void
    {
        $purchase = new Purchase();
        $payment = new Payment();
        $this->paymentRepository->method('find')->with(42)->willReturn($payment);

        $event = $this->createTransitionEvent($purchase, [
            'performed_by' => 'client',
            'source' => 'gpw',
            'paymentId' => 42,
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame(
            [$purchase, 'state_paid', 'client', null, ['source' => 'gpw'], $payment],
            $this->paymentActionLogger->calls[0],
        );
    }

    public function testOnPaymentDefaultsToSystemWhenContextMissing(): void
    {
        $purchase = new Purchase();

        $event = $this->createTransitionEvent($purchase, []);

        $this->subscriber->onPayment($event);

        $this->assertSame(
            [$purchase, 'state_paid', 'system', null, [], null],
            $this->paymentActionLogger->calls[0],
        );
    }

    public function testOnPaymentIssueLogsStateFailed(): void
    {
        $purchase = new Purchase();

        $event = $this->createTransitionEvent($purchase, ['performed_by' => 'client']);

        $this->subscriber->onPaymentIssue($event);

        $this->assertSame(
            [$purchase, 'state_failed', 'client', null, [], null],
            $this->paymentActionLogger->calls[0],
        );
    }

    public function testOnRefundLogsStateRefunded(): void
    {
        $purchase = new Purchase();

        $event = $this->createTransitionEvent($purchase, [
            'performed_by' => 'admin',
            'actor' => 'admin@example.com',
        ]);

        $this->subscriber->onRefund($event);

        $this->assertSame(
            [$purchase, 'state_refunded', 'admin', null, ['actor' => 'admin@example.com'], null],
            $this->paymentActionLogger->calls[0],
        );
    }

    public function testOnCancellationLogsStateCancelled(): void
    {
        $purchase = new Purchase();

        $event = $this->createTransitionEvent($purchase, []);

        $this->subscriber->onCancellation($event);

        $this->assertSame(
            [$purchase, 'state_cancelled', 'system', null, [], null],
            $this->paymentActionLogger->calls[0],
        );
    }

    private function createTransitionEvent(Purchase $purchase, array $context): TransitionEvent
    {
        return new TransitionEvent(
            $purchase,
            $this->createMock(Marking::class),
            $this->createMock(Transition::class),
            null,
            $context,
        );
    }
}
