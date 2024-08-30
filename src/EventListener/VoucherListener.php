<?php

namespace Greendot\EshopBundle\EventListener;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Registry;

class VoucherListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Registry               $registry,
        private readonly EntityManagerInterface $entityManager,
    ){}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.voucher_flow.transition.payment' => ['onPayment'],
            'workflow.voucher_flow.transition.payment_issue' => ['onPaymentIssue'],
            'workflow.voucher_flow.transition.use' => ['onUse'],
        ];
    }

    public function handleVouchers(Purchase $purchase, string $state): void
    {
        $vouchers = $purchase->getVouchersIssued();
        foreach ($vouchers as $voucher) {
            $workflow = $this->registry->get($voucher);
            if ($workflow->can($voucher, $state)) {
                $workflow->apply($voucher, $state);
            }
        }
    }

    public function onPayment(Event $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof Voucher) {
            throw new \LogicException('Expected subject of type Voucher, got ' . get_class($subject));
        }

        $voucher = $subject;

        $dateNow = new \DateTime();
        $voucher->setDateIssued($dateNow);
        $voucher->setDateUntil(($dateNow)->modify('+6 months'));

        $this->entityManager->flush();
    }

    public function onPaymentIssue(Event $event): void
    {
    }

    public function onUse(Event $event): void
    {
    }
}