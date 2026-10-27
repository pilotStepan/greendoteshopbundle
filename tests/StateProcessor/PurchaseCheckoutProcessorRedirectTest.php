<?php

namespace Greendot\EshopBundle\Tests\StateProcessor;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentGatewayProvider;
use Greendot\EshopBundle\StateProcessor\PurchaseCheckoutProcessor;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Tests\Stub\FakeGpWebpayGateway;
use Greendot\EshopBundle\Tests\Stub\PurchaseFlowFixtures;
use Greendot\EshopBundle\Tests\Stub\RecordingPaymentActionLogger;

/**
 * Exercises where checkout sends the client right after the purchase is accepted
 * (PurchaseCheckoutProcessor::buildRedirectUrl(), called at the end of process()).
 *
 * process() itself needs a session cart, validator and client/address handling, none
 * of which affect the redirect decision, so the private method is invoked directly.
 * The purchase still goes through a *real* Workflow + PurchaseStateSubscriber.
 */
class PurchaseCheckoutProcessorRedirectTest extends TestCase
{
    use PurchaseFlowFixtures;

    private const GATEWAY_URL = 'https://gateway.example.com/pay';

    private WorkflowInterface $workflow;
    private RecordingPaymentActionLogger $paymentActionLogger;

    protected function setUp(): void
    {
        $this->paymentActionLogger = new RecordingPaymentActionLogger();
        $this->workflow = $this->buildPurchaseWorkflow($this->paymentActionLogger);
    }

    /**
     * Order 14270: 1790 CZK paid entirely by an 1800 CZK gift voucher, card payment chosen.
     * GP webpay rejects AMOUNT=0, so the gateway must not be asked at all.
     */
    public function testZeroTotalCardOrderIsSettledAndSkipsGateway(): void
    {
        $purchase = $this->createCheckedOutPurchase(PaymentTechnicalAction::GLOBAL_PAYMENTS);

        // No payLinkResult configured: getPayLink() would throw if called.
        $url = $this->buildRedirectUrl($purchase, new FakeGpWebpayGateway(), new Money(0.0, 'CZK'));

        $this->assertSame(self::ENDSCREEN_URL, $url);
        $this->assertTrue($purchase->isPaid());
        $this->assertFalse($purchase->getWorkflowFlag(PWC::F_PAYMENT_ERROR->value));
        $this->assertSame(['state_paid'], $this->loggedActionNames());
    }

    public function testZeroTotalBankTransferOrderIsSettled(): void
    {
        $purchase = $this->createCheckedOutPurchase(null);

        $url = $this->buildRedirectUrl($purchase, new FakeGpWebpayGateway(), new Money(0.0, 'CZK'));

        $this->assertSame(self::ENDSCREEN_URL, $url);
        $this->assertTrue($purchase->isPaid());
        $this->assertSame(['state_paid'], $this->loggedActionNames());
    }

    public function testPositiveTotalCardOrderRedirectsToGateway(): void
    {
        $purchase = $this->createCheckedOutPurchase(PaymentTechnicalAction::GLOBAL_PAYMENTS);

        $url = $this->buildRedirectUrl($purchase, new FakeGpWebpayGateway(payLinkResult: self::GATEWAY_URL), new Money(1790.0, 'CZK'));

        $this->assertSame(self::GATEWAY_URL, $url);
        $this->assertFalse($purchase->isPaid());
        $this->assertSame([], $this->loggedActionNames());
    }

    public function testPositiveTotalBankTransferOrderStaysPendingOnEndscreen(): void
    {
        $purchase = $this->createCheckedOutPurchase(null);

        $url = $this->buildRedirectUrl($purchase, new FakeGpWebpayGateway(), new Money(1790.0, 'CZK'));

        $this->assertSame(self::ENDSCREEN_URL, $url);
        $this->assertFalse($purchase->isPaid());
        $this->assertSame([], $this->loggedActionNames());
    }

    private function buildRedirectUrl(Purchase $purchase, FakeGpWebpayGateway $gateway, Money $total): string
    {
        $processor = new PurchaseCheckoutProcessor(
            $this->createMock(EntityManagerInterface::class),
            $this->workflow,
            $this->createMock(Security::class),
            $this->createMock(ValidatorInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(RequestStack::class),
            new PaymentGatewayProvider([$gateway]),
            $this->buildEndscreenUrlGenerator(),
            $this->buildManagePurchase($this->workflow, $total),
            new ListenerManager(),
        );

        return (new \ReflectionMethod($processor, 'buildRedirectUrl'))->invoke($processor, $purchase);
    }

    private function createCheckedOutPurchase(?PaymentTechnicalAction $paymentTechnicalAction): Purchase
    {
        $purchase = new Purchase();
        $purchase->setClient((new Client())->setIsAnonymous(false));
        $purchase->setPaymentType((new PaymentType())->setPaymentTechnicalAction($paymentTechnicalAction));
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);

        return $purchase;
    }

    /** @return list<string> */
    private function loggedActionNames(): array
    {
        return array_map(static fn(array $call) => $call[1], $this->paymentActionLogger->calls->getArrayCopy());
    }
}
