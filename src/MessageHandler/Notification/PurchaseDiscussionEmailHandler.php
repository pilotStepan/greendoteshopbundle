<?php

namespace Greendot\EshopBundle\MessageHandler\Notification;

use RuntimeException;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Message\Notification\PurchaseDiscussionEmail;

#[AsMessageHandler]
readonly class PurchaseDiscussionEmailHandler
{
    public function __construct(
        private ManageMails            $manageMails,
        private PurchaseRepository     $purchaseRepository,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(PurchaseDiscussionEmail $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            throw new RuntimeException('Purchase not found for ID: ' . $purchaseId);
        }

        $discussion = $this->createPurchaseDiscussion($purchase, $msg);
        $this->em->persist($discussion);
        $this->em->flush();

        $this->manageMails->sendPurchaseDiscussionEmail($purchase);
    }

    private function createPurchaseDiscussion(Purchase $purchase, PurchaseDiscussionEmail $msg): PurchaseDiscussion
    {
        return (new PurchaseDiscussion())
            ->setPurchase($purchase)
            ->setContent($msg->content)
            ->setCreatedAt($msg->createdAt)
            ->setIsAdmin(true)
            ->setIsRead(false)
        ;
    }
}