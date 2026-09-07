<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Exception;
use LogicException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Workflow\Event\Event;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Service\ManagePurchase;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\DataLayer\Event\PurchaseEvent;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Enum\PaymentActionType;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

readonly class PurchaseStateSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private EntityManagerInterface   $entityManager,
        private ManageVoucher            $manageVoucher,
        private ManagePurchase           $managePurchase,
        private ManageClientDiscount     $manageClientDiscount,
        private DateService              $dateService,
        private EventDispatcherInterface $eventDispatcher,
        #[Target('purchase_flow')]
        private WorkflowInterface        $purchaseWorkflow,
        private PaymentActionLogger      $paymentActionLogger,
        private PaymentRepository        $paymentRepository,
        ?LoggerInterface                 $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PWC::eventName('guard', PWC::T_CHECKOUT) => 'onGuardReceive',
            PWC::eventName('guard', PWC::T_INIT_ORDER) => 'onGuardCurrencyConsistency',

            PWC::eventName('transition', PWC::T_CHECKOUT) => 'onReceive',
            PWC::eventName('transition', PWC::T_INIT_ORDER) => 'onInitOrder',
            PWC::eventName('transition', PWC::T_PAY_PAY) => 'onPayment',
            PWC::eventName('transition', PWC::T_PAY_FAIL) => 'onPaymentIssue',
            PWC::eventName('transition', PWC::T_CANCEL) => 'onCancellation',
            PWC::eventName('transition', PWC::T_PAY_REFUND) => 'onRefund',

            // Terminal cleanup
            PWC::eventName('entered', PWC::S_CANCELLED) => 'onEnterCancelled',
            PWC::eventName('entered', PWC::S_COMPLETED) => 'onEnterCompleted',

            // Funnels
            PWC::eventName('completed', PWC::T_LOG_DELIVER) => 'onTrackFinished',
            PWC::eventName('completed', PWC::T_LOG_PICKUP_DONE) => 'onTrackFinished',
            PWC::eventName('completed', PWC::T_PAY_PAY) => 'onTrackFinished',
        ];
    }

    public function onGuardReceive(GuardEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        if ($purchase->getProductVariants()->isEmpty()) {
            $event->setBlocked(true, 'Nelze vytvořit prázdnou objednávku');
            return;
        }

        foreach ($purchase->getProductVariants() as $ppv) {
            // pv doesn't have an availability set => it's purchasable
            if ($ppv->getProductVariant()->getAvailability()?->getIsPurchasable() === false) {
                $event->setBlocked(true, 'V košíku jsou nedostupné položky, než budete pokračovat, prosím je odeberte');
                return;
            }
        }

        if (!$purchase->getClient()) {
            $event->setBlocked(true, 'Objednávka musí mít přiřazeného klienta');
            return;
        }

        $paymentType = $purchase->getPaymentType();
        if (!$paymentType) {
            $event->setBlocked(true, "Nebyl vybrán typ platby");
            return;
        }

        $transportation = $purchase->getTransportation();
        if (!$transportation) {
            $event->setBlocked(true, "Nebyla vybrána doprava");
            return;
        }

        if (!$paymentType->getTransportations()->contains($transportation)) {
            $event->setBlocked(true, "Nekompatibilní typ platby a dopravy");
            return;
        }

        if (!$this->isCurrencyConsistent($purchase)) {
            $event->setBlocked(true, $this->currencyMismatchMessage($purchase));
            return;
        }

        $missingConsent = $this->entityManager->getRepository(Consent::class)->findMissingRequiredConsent($purchase->getConsents());

        if ($missingConsent) {
            $event->setBlocked(true, "Povinný souhlas nebyl zaškrtnut: " . $missingConsent->getDescription());
            return;
        }

        try {
            $this->manageVoucher->validateVouchersTransition($purchase->getVouchersUsed(), 'use');
        } catch (LogicException $e) {
            $event->setBlocked(true, $e->getMessage());
            return;
        }

        $discount = $purchase->getClientDiscount();
        if ($discount) {
            try {
                $this->manageClientDiscount->guardUse($discount, $purchase);
            } catch (LogicException $e) {
                $event->setBlocked(true, $e->getMessage());
            }
        }

        try {
            $this->managePurchase->processVatNumber($purchase);
        } catch (Exception $e) {
            $event->setBlocked(true, "Chyba při ověřování DIČ: " . $e->getMessage());
            return;
        }
    }

    public function onGuardCurrencyConsistency(GuardEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        if (!$this->isCurrencyConsistent($purchase)) {
            $event->setBlocked(true, $this->currencyMismatchMessage($purchase));
        }
    }

    private function isCurrencyConsistent(Purchase $purchase): bool
    {
        $purchaseCurrency = $purchase->getCurrency();
        $paymentTypeCurrency = $purchase->getPaymentType()?->getCurrency();

        return $purchaseCurrency === null
            || $paymentTypeCurrency === null
            || $purchaseCurrency === $paymentTypeCurrency;
    }

    private function currencyMismatchMessage(Purchase $purchase): string
    {
        return sprintf(
            'Objednávka je vedena v měně %s, ale zvolený způsob platby účtuje v %s.',
            $purchase->getCurrency()?->getIso() ?? '?',
            $purchase->getPaymentType()?->getCurrency()?->getIso() ?? '?',
        );
    }

    public function onInitOrder(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->managePurchase->ensureCurrency($purchase);
    }

    public function onReceive(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();

        try {
            $this->eventDispatcher->dispatch(new PurchaseEvent($purchase));
        } catch (Exception $exception) {
            //maybe add logg
        }

        foreach ($purchase->getVouchersUsed() as $voucher) {
            $this->manageVoucher->use($voucher, $purchase);
        }

        $clientDiscount = $purchase->getClientDiscount();
        if ($clientDiscount !== null) {
            $this->manageClientDiscount->use($clientDiscount, $purchase);
        }

        $this->manageVoucher->initiateVouchers($purchase);
        // transport data is generated on `log_prepare_to_ship` event instead
        // $this->managePurchase->generateTransportData($purchase);
        $this->dateService->calculatePurchaseDeliveryDate($purchase);
    }

    public function onPayment(TransitionEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->clearWorkflowFlag(PWC::F_PAYMENT_ERROR->value);
        $purchase->assignWorkflowFlag(PWC::F_PAYMENT_SUCCESS->value);

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment');

        $paymentTechnicalActionValue = $event->getContext()['payment_technical_action'] ?? null;
        $paymentTechnicalAction = is_string($paymentTechnicalActionValue) ? PaymentTechnicalAction::tryFrom($paymentTechnicalActionValue) : null;
        if ($paymentTechnicalAction) {
            $paymentTypeRepository = $this->entityManager->getRepository(PaymentType::class);

            $paymentType = $purchase->getCurrency()
                ? $paymentTypeRepository->findOneBy([
                    'paymentTechnicalAction' => $paymentTechnicalAction,
                    'currency' => $purchase->getCurrency(),
                    'isEnabled' => true,
                ])
                : null;

            $paymentType ??= $paymentTypeRepository->findOneBy([
                'paymentTechnicalAction' => $paymentTechnicalAction,
                'currency' => null,
                'isEnabled' => true,
            ]);

            if ($paymentType !== null) {
                $purchase->setPaymentType($paymentType);
            } else {
                $this->logger->warning('No matching enabled PaymentType found for successful payment; leaving existing PaymentType untouched', [
                    'purchaseId' => $purchase->getId(),
                    'paymentTechnicalAction' => $paymentTechnicalAction->value,
                    'purchaseCurrency' => $purchase->getCurrency()?->getIso(),
                ]);
            }
        }

        $this->logPaymentAction($purchase, $event, PaymentActionType::STATE_PAID);
    }

    public function onPaymentIssue(TransitionEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->assignWorkflowFlag(PWC::F_PAYMENT_ERROR->value);

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');

        $this->logPaymentAction($purchase, $event, PaymentActionType::STATE_FAILED);
    }

    public function onRefund(TransitionEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->clearWorkflowFlag(PWC::F_PAYMENT_SUCCESS->value);

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');

        $this->logPaymentAction($purchase, $event, PaymentActionType::STATE_REFUNDED);
    }

    public function onCancellation(TransitionEvent $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->manageVoucher->handleVouchersTransition($purchase->getVouchersIssued(), 'payment_issue');

        $this->logPaymentAction($purchase, $event, PaymentActionType::STATE_CANCELLED);
    }

    private function logPaymentAction(Purchase $purchase, TransitionEvent $event, PaymentActionType $name): void
    {
        $context = $event->getContext();
        $performedBy = $context['performed_by'] ?? 'system';

        $payment = null;
        if (isset($context['paymentId'])) {
            $payment = $this->paymentRepository->find($context['paymentId']);
        }

        unset($context['performed_by'], $context['paymentId']);

        $this->paymentActionLogger->log($purchase, $name->value, $performedBy, null, $context, $payment);
    }

    public function onEnterCancelled(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->setMarking([PWC::S_CANCELLED->value => 1]);
    }

    public function onEnterCompleted(Event $event): void
    {
        /** @var Purchase $purchase */
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
    }

    public function onTrackFinished(CompletedEvent $event): void
    {
        $purchase = $event->getSubject();
        if (!$purchase instanceof Purchase) {
            return;
        }

        $this->tryAutoComplete($purchase);
        $this->entityManager->flush();
    }

    private function tryAutoComplete(Purchase $purchase): void
    {
        if ($this->purchaseWorkflow->can($purchase, PWC::T_COMPLETE->value)) {
            $this->purchaseWorkflow->apply($purchase, PWC::T_COMPLETE->value);
        }
    }
}
