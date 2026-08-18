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
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
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
    private MockObject $currencyManager;
    private Currency $defaultCurrency;
    private PurchaseStateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        // Mirrors CurrencyManager::getForPurchase()'s real fallback chain, without
        // needing a session/repository: purchase's own payment-type currency, or
        // this test's shared "default currency" instance.
        $this->defaultCurrency = new Currency();
        $this->currencyManager = $this->createMock(CurrencyManager::class);
        $this->currencyManager->method('getForPurchase')->willReturnCallback(
            fn (Purchase $purchase) => $purchase->getPaymentType()?->getCurrency() ?? $this->defaultCurrency
        );

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
            $this->currencyManager,
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

    public function testOnPaymentSetsPaymentTypeMatchingValidTechnicalAction(): void
    {
        $purchase = new Purchase();
        $matchingPaymentType = new PaymentType();

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'paymentTechnicalAction' => PaymentTechnicalAction::GLOBAL_PAYMENTS,
                'currency' => $this->defaultCurrency,
            ])
            ->willReturn($matchingPaymentType);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
            'performed_by' => 'client',
            'source' => 'gpw',
        ]);

        $this->subscriber->onPayment($event);

        $this->assertTrue($purchase->isPaid());
        $this->assertSame($matchingPaymentType, $purchase->getPaymentType());
    }

    /**
     * Regression test: PaymentType rows come in same-technical-action pairs
     * (e.g. a CZK "(CZ)" row and a EUR "(SK)" row for the same gateway). A
     * currency-blind findOneBy() would silently flip a EUR purchase's payment
     * type — and therefore its currency, IBAN and locale — to the CZK sibling
     * on gateway return. The lookup must be constrained to the purchase's own
     * currency whenever a payment type is already attached.
     */
    public function testOnPaymentKeepsPurchaseCurrencyWhenResolvingTechnicalAction(): void
    {
        $eur = new Currency();
        $existingEurPaymentType = new PaymentType();
        $existingEurPaymentType->setCurrency($eur);

        $purchase = new Purchase();
        $purchase->setPaymentType($existingEurPaymentType);

        $matchingEurPaymentType = new PaymentType();
        $matchingEurPaymentType->setCurrency($eur);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'paymentTechnicalAction' => PaymentTechnicalAction::GLOBAL_PAYMENTS,
                'currency' => $eur,
            ])
            ->willReturn($matchingEurPaymentType);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame($matchingEurPaymentType, $purchase->getPaymentType());
        $this->assertSame($eur, $purchase->getPaymentType()->getCurrency());
    }

    /**
     * If no payment type exists for the purchase's own currency (e.g. data gap),
     * the customer's already-attached payment type must be left untouched rather
     * than overwritten with null or with a wrong-currency sibling.
     */
    public function testOnPaymentLeavesPaymentTypeUntouchedWhenNoMatchInPurchaseCurrency(): void
    {
        $eur = new Currency();
        $existingEurPaymentType = new PaymentType();
        $existingEurPaymentType->setCurrency($eur);

        $purchase = new Purchase();
        $purchase->setPaymentType($existingEurPaymentType);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'paymentTechnicalAction' => PaymentTechnicalAction::GLOBAL_PAYMENTS,
                'currency' => $eur,
            ])
            ->willReturn(null);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame($existingEurPaymentType, $purchase->getPaymentType(), 'Payment type must be left untouched when nothing matches the purchase currency');
    }

    /**
     * Regression test: legacy PaymentType rows predate the currency column and
     * have currency = null. The currency constraint must never be dropped from
     * the lookup criteria in that case (that would resurrect the currency-blind
     * bug above) - it must fall back to the purchase's resolved default currency.
     */
    public function testOnPaymentConstrainsByDefaultCurrencyWhenPaymentTypeCurrencyIsMissing(): void
    {
        $existingPaymentTypeWithoutCurrency = new PaymentType();
        // currency intentionally left unset (legacy row)

        $purchase = new Purchase();
        $purchase->setPaymentType($existingPaymentTypeWithoutCurrency);

        $matchingPaymentType = new PaymentType();
        $matchingPaymentType->setCurrency($this->defaultCurrency);

        $paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $paymentTypeRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'paymentTechnicalAction' => PaymentTechnicalAction::GLOBAL_PAYMENTS,
                'currency' => $this->defaultCurrency,
            ])
            ->willReturn($matchingPaymentType);
        $this->entityManager->method('getRepository')->with(PaymentType::class)->willReturn($paymentTypeRepository);

        $event = $this->createTransitionEvent($purchase, [
            'payment_technical_action' => PaymentTechnicalAction::GLOBAL_PAYMENTS->value,
        ]);

        $this->subscriber->onPayment($event);

        $this->assertSame($matchingPaymentType, $purchase->getPaymentType());
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
