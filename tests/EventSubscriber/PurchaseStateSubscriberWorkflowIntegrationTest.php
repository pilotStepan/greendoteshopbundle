<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

/**
 * Wires PurchaseStateSubscriber to a real Symfony Workflow, mirroring the
 * actual purchase_flow definition, instead of calling
 * onPayment()/onPaymentIssue() directly. This exercises the same
 * dispatch path used in production by ManagePurchase::applyBankTransferPayment()
 * (called from RbBankPaymentImportService for matched RB bank statement rows),
 * which never includes a 'payment_technical_action' key in the transition
 * context — only client-facing GPWebpay flows do.
 */
class PurchaseStateSubscriberWorkflowIntegrationTest extends TestCase
{
    use PurchaseFlowFixtures;

    private MockObject $entityManager;
    private WorkflowInterface $workflow;
    private ManagePurchase $managePurchase;
    private RecordingPaymentActionLogger $paymentActionLogger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->workflow = $this->buildPurchaseWorkflow($this->paymentActionLogger, $this->entityManager);
        $this->managePurchase = $this->buildManagePurchase($this->workflow);
    }

    public function testRealBankTransferPaymentTransitionDoesNotCrashAndMarksPurchasePaid(): void
    {
        $purchase = new Purchase();
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
        $paymentType = new PaymentType();

        $this->entityManager->expects($this->never())->method('getRepository');

        // Exact context shape RbBankPaymentImportService::processRecord() builds:
        // no 'payment_technical_action' key.
        $this->managePurchase->applyBankTransferPayment($purchase, $paymentType, [
            'performed_by' => 'system',
            'source' => 'rb_bank',
            'variableSymbol' => '123',
            'transactionId' => 'tx-1',
        ]);

        $this->assertTrue($purchase->isPaid());
        $this->assertSame($paymentType, $purchase->getPaymentType());
    }

    /**
     * Voucher covering the whole total (order 14270: 1790 CZK mat, 1800 CZK gift voucher) -
     * the purchase must end up paid through the real pay_pay transition, keeping its chosen
     * PaymentType, with the state_paid audit row recorded.
     */
    public function testZeroTotalPurchaseIsSettledThroughRealPayTransition(): void
    {
        $paymentType = new PaymentType();

        $purchase = new Purchase();
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
        (new \ReflectionProperty(Purchase::class, 'PaymentType'))->setValue($purchase, $paymentType);

        $managePurchase = $this->buildManagePurchase($this->workflow, new Money(0.0, 'CZK'));

        $this->assertTrue($managePurchase->settleIfNothingToPay($purchase, [
            'performed_by' => 'client',
            'source' => 'checkout_zero_total',
        ]));

        $this->assertTrue($purchase->isPaid());
        $this->assertSame($paymentType, $purchase->getPaymentType());
        $this->assertCount(1, $this->paymentActionLogger->calls);
        [, $name, $performedBy, , $data] = $this->paymentActionLogger->calls[0];
        $this->assertSame('state_paid', $name);
        $this->assertSame('client', $performedBy);
        $this->assertSame('checkout_zero_total', $data['source']);
        $this->assertSame(0.0, $data['amount']);
    }
}
