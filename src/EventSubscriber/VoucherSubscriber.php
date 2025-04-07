<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
//use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Registry;

class VoucherSubscriber implements EventSubscriberInterface
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
            'workflow.voucher_flow.guard.use' => ['guardUse'],
            'workflow.voucher_flow.transition.use' => ['onUse'],
        ];
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

    public function guardUse(GuardEvent $event): void
    {
        /** @var  Voucher $voucher */
        $voucher = $event->getSubject();

        // check date_until
        $now = new \DateTime();
        if ($now <= $voucher->getDateUntil()){
            $event->setBlocked(true, "Voucher has expired");
        }

    }

    public function onUse(Event $event): void
    {

    }
}