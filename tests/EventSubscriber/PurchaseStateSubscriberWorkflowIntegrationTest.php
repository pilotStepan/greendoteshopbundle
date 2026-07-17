<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\Transition;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Workflow\DefinitionBuilder;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Greendot\EshopBundle\EventSubscriber\PurchaseStateSubscriber;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
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
    private MockObject $entityManager;
    private WorkflowInterface $workflow;
    private ManagePurchase $managePurchase;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $definition = (new DefinitionBuilder(
            ['draft', 'completed'],
            [new Transition(PWC::T_PAY_PAY->value, PWC::S_COMPLETED->value, PWC::S_COMPLETED->value)],
        ))->build();

        $dispatcher = new EventDispatcher();
        $subscriber = new PurchaseStateSubscriber(
            $this->entityManager,
            $this->createMock(ManageVoucher::class),
            $this->buildManagePurchase($this->createMock(WorkflowInterface::class)),
            new ManageClientDiscount($this->createMock(EntityManagerInterface::class)),
            $this->createMock(DateService::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(WorkflowInterface::class),
            new RecordingPaymentActionLogger(),
            $this->createMock(PaymentRepository::class),
        );
        $dispatcher->addSubscriber($subscriber);

        // Production config (config/packages/workflow.yaml) declares purchase_flow with
        // type: 'workflow' (not 'state_machine'), so the marking store is multi-state
        // (singleState=false), matching Purchase::$marking being an array.
        $this->workflow = new Workflow($definition, new MethodMarkingStore(false, 'marking'), $dispatcher, PWC::NAME->value);

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

    private function buildManagePurchase(WorkflowInterface $workflow): ManagePurchase
    {
        return new ManagePurchase(
            $this->createMock(\Greendot\EshopBundle\Service\CurrencyManager::class),
            $this->createMock(\Greendot\EshopBundle\Service\Price\PurchasePriceFactory::class),
            $this->createMock(\Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory::class),
            $this->createMock(\Greendot\EshopBundle\Repository\Project\PurchaseRepository::class),
            new \Greendot\EshopBundle\Service\Vies\ManageVies($this->createMock(\Psr\Log\LoggerInterface::class)),
            $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class),
            $this->createMock(\Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface::class),
            $workflow,
        );
    }
}
