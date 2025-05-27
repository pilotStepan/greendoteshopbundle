<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Message;

/**
 * Single repository for all Discussion, including PurchaseDiscussion and Review.
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Find all PurchaseDiscussions.
     */
    public function findPurchaseDiscussions(): array
    {
        return $this->createQueryBuilder('d')
            ->where('TYPE(d) = :type')
            ->setParameter('type', 'App\\Entity\\Project\\PurchaseDiscussion')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unread PurchaseDiscussions (isRead = false).
     */
    public function findUnreadPurchaseDiscussions(): array
    {
        return $this->createQueryBuilder('d')
            ->where('TYPE(d) = :type')
            ->setParameter('type', 'App\\Entity\\Project\\PurchaseDiscussion')
            ->andWhere('d.isRead = false')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
