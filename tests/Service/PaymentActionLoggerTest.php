<?php

namespace Greendot\EshopBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;

class PaymentActionLoggerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PaymentActionLogger $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = new PaymentActionLogger($this->entityManager);
    }

    public function testLogPersistsPaymentActionWithPurchaseAndData(): void
    {
        $purchase = new Purchase();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(PaymentAction::class));

        $action = $this->logger->log(
            $purchase,
            'state_paid',
            'system',
            'Paid via bank transfer',
            ['source' => 'rb_bank', 'amount' => 99.5],
        );

        $this->assertSame($purchase, $action->getPurchase());
        $this->assertNull($action->getPayment());
        $this->assertSame('state_paid', $action->getName());
        $this->assertSame('system', $action->getPerformedBy());
        $this->assertSame('Paid via bank transfer', $action->getDescription());
        $this->assertSame(
            ['source' => 'rb_bank', 'amount' => 99.5],
            json_decode($action->getData(), true),
        );
    }

    public function testLogAttachesOptionalPayment(): void
    {
        $purchase = new Purchase();
        $payment = new Payment();

        $this->entityManager->expects($this->once())->method('persist');

        $action = $this->logger->log(
            $purchase,
            'gpw_redirect',
            'client',
            null,
            [],
            $payment,
        );

        $this->assertSame($payment, $action->getPayment());
    }

    public function testLogDefaultsDataToEmptyJsonObject(): void
    {
        $purchase = new Purchase();

        $action = $this->logger->log($purchase, 'state_failed', 'system');

        $this->assertSame('[]', $action->getData());
    }
}
