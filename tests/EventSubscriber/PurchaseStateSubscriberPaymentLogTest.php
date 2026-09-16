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
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
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
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

class PurchaseStateSubscriberPaymentLogTest extends TestCase
{
    private MockObject $entityManager;
    private RecordingPaymentActionLogger $paymentActionLogger;
    private MockObject $paymentRepository;
    private PurchaseStateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        $this->subscriber = new PurchaseStateSubscriber(
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

    /**
     * Regression test: ManagePurchase::applyBankTransferPayment() (called by
     * RbBankPaymentImportService for RB bank statement matches) applies pay_pay
     * without a 'payment_technical_action' key in the context. Previously this
     * fed `null` straight into PaymentTechnicalAction::tryFrom(), which is typed
     * `int|string` and does not accept null, fataling every bank-transfer payment.
     */
    public function testOnPaymentDoesNotCrashWhenTechnicalActionMissingFromContext(): void
    {
        $purchase = new Purchase();
        $originalPaymentType = new PaymentType();
        $purchase->setPaymentType($originalPaymentType);

        $this->entityManager->expects($this->never())->method('getRepository');

        $event = $this->createTransitionEvent($purchase, [
            'performed_by' => 'system',
            'source' => 'rb_bank',
            'variableSymbol' => '123',
        ]);

        $this->subscriber->onPayment($event);

        $this->assertTrue($purchase->isPaid());
        $this->assertSame($originalPaymentType, $purchase->getPaymentType(), 'Payment type must be left untouched when no technical action is given');
    }

    public function testOnPaymentDoesNotCrashWhenTechnicalActionIsUnknownString(): void
    {
        $purchase = new Purchase();

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->expects($this->never())->method('findOneBy');
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => 'not_a_real_gateway',
        ]);

        $this->subscriber->onPayment($event);

        $this->assertTrue($purchase->isPaid());
        $this->assertNull($purchase->getPaymentType());
    }

    /**
     * Defensive regression test: even if a caller passes a non-string, non-null
     * value (e.g. an int or array) for 'payment_technical_action', onPayment must
     * not pass it to tryFrom() and must not crash.
     */
    public function testOnPaymentDoesNotCrashWhenTechnicalActionIsNonStringType(): void
    {
        $purchase = new Purchase();

        $this->entityManager->expects($this->never())->method('getRepository');

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => 123,
        ]);

        $this->subscriber->onPayment($event);

        $this->assertTrue($purchase->isPaid());
        $this->assertNull($purchase->getPaymentType());
    }

    public function testOnPaymentKeepsCustomersExistingChoiceWhenItAlreadyMatchesTechnicalAction(): void
    {
        $skCard = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS);
        $purchase = new Purchase();
        $purchase->setPaymentType($skCard);

        $this->entityManager->expects($this->never())->method('getRepository');

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
            'performed_by' => 'client',
            'source' => 'gpw',
        ]);

        $this->subscriber->onPayment($event);

        $this->assertTrue($purchase->isPaid());
        $this->assertSame($skCard, $purchase->getPaymentType());
    }

    public function testOnPaymentOnlySwapsToATransportationCompatiblePaymentType(): void
    {
        $czCard = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS)->setIsEnabled(true);
        $skCard = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS)->setIsEnabled(true);

        $transportation = (new Transportation())->addPaymentType($skCard);

        $purchase = new Purchase();
        $purchase->setTransportation($transportation);
        $purchase->setPaymentType((new PaymentType())->setPaymentTechnicalAction(null)); // e.g. bank transfer

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->expects($this->once())
            ->method('findBy')
            ->with(['paymentTechnicalAction' => PaymentTechnicalAction::GLOBAL_PAYMENTS])
            ->willReturn([$czCard, $skCard]);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
            'performed_by' => 'client',
            'source' => 'gpw',
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame($skCard, $purchase->getPaymentType());
    }

    public function testOnPaymentPrefersPaymentTypeMatchingPurchaseCurrencyAmongCompatibleOnes(): void
    {
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2);
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0);

        $czkPaymentType = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS)->setCurrency($czk)->setIsEnabled(true);
        $eurPaymentType = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS)->setCurrency($eur)->setIsEnabled(true);

        $transportation = (new Transportation())->addPaymentType($czkPaymentType)->addPaymentType($eurPaymentType);

        $purchase = (new Purchase())->setCurrency($eur);
        $purchase->setTransportation($transportation);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->method('findBy')->willReturn([$czkPaymentType, $eurPaymentType]);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
            'performed_by' => 'client',
            'source' => 'gpw',
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame($eurPaymentType, $purchase->getPaymentType());
        $this->assertSame($eur, $purchase->getCurrency(), 'Must stay EUR, not be overwritten');
    }

    public function testOnPaymentPrefersEnabledOverDisabledWhenCurrencyDoesNotDiscriminate(): void
    {
        $disabled = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS)->setIsEnabled(false);
        $enabled = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS)->setIsEnabled(true);

        $transportation = (new Transportation())->addPaymentType($disabled)->addPaymentType($enabled);

        $purchase = new Purchase();
        $purchase->setTransportation($transportation);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->method('findBy')->willReturn([$disabled, $enabled]);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame($enabled, $purchase->getPaymentType());
    }

    /**
     * Regression test: previously, when a valid technical action matched no enabled PaymentType
     * at all (e.g. a gateway with no rows configured), the bare `setPaymentType($paymentType)`
     * call still ran with $paymentType === null, wiping out whatever PaymentType the purchase
     * already had on a *successful* payment - breaking COD detection, QR/invoice bank details,
     * and PurchaseCheckoutProcessor's post-checkout redirect.
     */
    public function testOnPaymentLeavesPaymentTypeUntouchedAndWarnsWhenNoTechnicalActionMatchConfigured(): void
    {
        $purchase = new Purchase();
        $originalPaymentType = new PaymentType();
        $purchase->setPaymentType($originalPaymentType);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->method('findBy')->willReturn([]);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            $this->stringContains('No PaymentType configured'),
        );

        $subscriber = new PurchaseStateSubscriber(
            $this->entityManager,
            $this->createMock(ManageVoucher::class),
            $this->buildManagePurchase(),
            new ManageClientDiscount($this->createMock(EntityManagerInterface::class)),
            $this->createMock(DateService::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(WorkflowInterface::class),
            $this->paymentActionLogger,
            $this->paymentRepository,
            $logger,
        );

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
        ]);

        $subscriber->onPayment($event);

        $this->assertTrue($purchase->isPaid());
        $this->assertSame($originalPaymentType, $purchase->getPaymentType(), 'Payment type must be left untouched when no match is found');
    }

    /**
     * The counterpart of the fix: candidates exist for the technical action, but none of them
     * are accepted by the purchase's Transportation. Assigning any of them would recreate the
     * exact "Nekompatibilní typ platby a dopravy" bug this resolver exists to prevent, so the
     * existing PaymentType must be left untouched instead.
     */
    public function testOnPaymentLeavesPaymentTypeUntouchedAndWarnsWhenNoneCompatibleWithTransportation(): void
    {
        $czCard = (new PaymentType())->setPaymentTechnicalAction(PaymentTechnicalAction::GLOBAL_PAYMENTS);
        // Transportation only accepts a different (unrelated) PaymentType.
        $transportation = (new Transportation())->addPaymentType(new PaymentType());

        $purchase = new Purchase();
        $purchase->setTransportation($transportation);
        $originalPaymentType = new PaymentType();
        $purchase->setPaymentType($originalPaymentType);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->method('findBy')->willReturn([$czCard]);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            $this->stringContains('compatible with'),
        );

        $subscriber = new PurchaseStateSubscriber(
            $this->entityManager,
            $this->createMock(ManageVoucher::class),
            $this->buildManagePurchase(),
            new ManageClientDiscount($this->createMock(EntityManagerInterface::class)),
            $this->createMock(DateService::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(WorkflowInterface::class),
            $this->paymentActionLogger,
            $this->paymentRepository,
            $logger,
        );

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
        ]);

        $subscriber->onPayment($event);

        $this->assertSame($originalPaymentType, $purchase->getPaymentType(), 'Payment type must be left untouched when no compatible candidate exists');
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
