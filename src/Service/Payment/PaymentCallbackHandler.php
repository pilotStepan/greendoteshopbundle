<?php

namespace Greendot\EshopBundle\Service\Payment;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Greendot\EshopBundle\Enum\PaymentOutcome;
use Greendot\EshopBundle\Enum\PaymentCallbackSource;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentResult;
use Greendot\EshopBundle\Service\PaymentGateway\UnresolvedPaymentException;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentCallbackGatewayInterface;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

/**
 * Applies a gateway callback (client return or server notification) to the purchase.
 *
 * Shared by all gateways implementing PaymentCallbackGatewayInterface: the gateway confirms which of our
 * payments the callback belongs to and its outcome, this class decides what happens to the purchase.
 */
#[WithMonologChannel('purchase.checkout')]
readonly class PaymentCallbackHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target(PWC::NAME->value)]
        private WorkflowInterface      $purchaseWorkflow,
        private LockFactory            $lockFactory,
        private PaymentActionLogger    $paymentActionLogger,
        private LoggerInterface        $logger,
    ) {}

    /**
     * @param PaymentCallbackGatewayInterface $gateway
     * @param Request $request
     * @param PaymentCallbackSource $source
     * @return Purchase|null
     */
    public function handle(PaymentCallbackGatewayInterface $gateway, Request $request, PaymentCallbackSource $source): ?Purchase
    {
        $action = $gateway::action();
        $context = [
            'payment_technical_action' => $action->value,
            'performed_by' => $source->performedBy(),
            'source' => $action->value,
            'callback' => $source->value,
            'query' => $request->query->all(),
        ];

        try {
            $result = $gateway->resolveCallback($request);
        } catch (UnresolvedPaymentException $e) {
            $this->logger->warning('Payment callback does not match any of our payments', $context + ['message' => $e->getMessage()]);
            return null;
        } catch (Throwable $e) {
            $this->logger->error('Payment callback could not be resolved - payment outcome unknown', $context + [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            return null;
        }

        $payment = $result->payment;
        $purchase = $payment->getPurchase();
        unset($context['query']);
        $context += [
            'purchaseId' => $purchase->getId(),
            'paymentId' => $payment->getId(),
            'externalId' => $payment->getExternalId(),
            'outcome' => $result->outcome->value,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency()?->getIso(),
        ];

        // Return and notification usually arrive at the same moment - without the lock both could apply pay_pay
        $lock = $this->lockFactory->createLock('purchase_payment_' . $purchase->getId());
        $lock->acquire(true);

        try {
            // Another request may have changed the purchase while we were waiting for the lock
            $this->entityManager->refresh($purchase);

            $this->paymentActionLogger->log(
                $purchase,
                sprintf('%s_%s', $action->value, $source->value),
                $source->performedBy(),
                null,
                $request->query->all() + $request->request->all(),
                $payment,
            );

            $this->apply($purchase, $result, $context);

            $this->entityManager->flush();

            return $purchase;
        } catch (Throwable $e) {
            $this->logger->error('Error while applying payment callback - payment outcome could not be processed', $context + [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $source === PaymentCallbackSource::NOTIFY ? null : $purchase;
        } finally {
            $lock->release();
        }
    }

    private function apply(Purchase $purchase, PaymentResult $result, array $context): void
    {
        if ($purchase->isPaid()) {
            $this->logger->info('Ignoring payment callback for an already-paid purchase', $context);
            return;
        }

        switch ($result->outcome) {
            case PaymentOutcome::PAID:
                if (!$this->matchesPayment($result)) {
                    $this->logger->error('Paid amount does not match our payment - purchase left unpaid, check it manually', $context + [
                        'gatewayAmount' => $result->amount,
                        'gatewayCurrency' => $result->currency,
                    ]);
                    return;
                }

                $this->applyTransitionIfPossible($purchase, PWC::T_PAY_PAY->value, $context);
                return;

            case PaymentOutcome::FAILED:
                // A timeout or cancellation of an abandoned attempt must not flag the attempt the client is paying now
                $latest = $this->latestPayment($purchase);
                if ($latest !== null && $latest->getId() !== $result->payment->getId()) {
                    $this->logger->info('Ignoring failure of an older payment attempt', $context);
                    return;
                }

                $this->applyTransitionIfPossible($purchase, PWC::T_PAY_FAIL->value, $context);
                return;

            case PaymentOutcome::PENDING:
                $this->logger->info('Payment has no final outcome yet', $context);
                return;
        }
    }

    private function matchesPayment(PaymentResult $result): bool
    {
        $payment = $result->payment;

        if ($result->amount !== null && $payment->getAmount() !== null
            && $result->amount !== (int)round($payment->getAmount() * 100)
        ) {
            return false;
        }

        $currency = $payment->getCurrency()?->getIso();

        return $result->currency === null || $currency === null || strcasecmp($result->currency, $currency) === 0;
    }

    private function latestPayment(Purchase $purchase): ?Payment
    {
        $latest = null;
        foreach ($purchase->getPayments() as $payment) {
            if ($latest === null || $payment->getId() > $latest->getId()) {
                $latest = $payment;
            }
        }

        return $latest;
    }

    private function applyTransitionIfPossible(Purchase $purchase, string $transition, array $context): void
    {
        if (!$this->purchaseWorkflow->can($purchase, $transition)) {
            $this->logger->warning('Skipping payment callback transition: not applicable to current purchase state', [...$context, 'transition' => $transition]);
            return;
        }

        $this->purchaseWorkflow->apply($purchase, $transition, $context);
    }
}
