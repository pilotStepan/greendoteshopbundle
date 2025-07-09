<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;

readonly class VoucherStateSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.voucher_flow.transition.payment' => ['onPayment'],
            'workflow.voucher_flow.guard.use' => ['guardUse'],
        ];
    }


    public function onPayment(Event $event): void
    {
        /** @var Voucher $voucher */
        $voucher = $event->getSubject();

        $this->entityManager->wrapInTransaction(function() use ($voucher) {
            $dateNow = new \DateTime();
            $voucher->setDateIssued($dateNow);
            $voucher->setDateUntil(($dateNow)->modify('+6 months'));
        });
    }

    public function guardUse(GuardEvent $event): void
    {
        /** @var  Voucher $voucher */
        $voucher = $event->getSubject();

        if (new \DateTime() > $voucher->getDateUntil()) {
            $event->setBlocked(true, "Platnost voucheru vypršela: " . $voucher->getHash());
        }
    }
}