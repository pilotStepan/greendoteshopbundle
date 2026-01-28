<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Watchdog;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogState;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Watchdog>
 */
class WatchdogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Watchdog::class);
    }

    /**
     * @return Watchdog[]
     */
    public function findActiveVariantAvailableByVariantId(int $productVariantId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.type = :type')
            ->andWhere('w.state = :state')
            ->andWhere('IDENTITY(w.productVariant) = :variantId')
            ->setParameter('type', WatchdogType::VariantAvailable)
            ->setParameter('state', WatchdogState::Active)
            ->setParameter('variantId', $productVariantId)
            ->getQuery()
            ->getResult()
        ;
    }

    /* Used by UniqueEntity constraint */
    public function isActiveUnique(array $fields): ?Watchdog
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.type = :type')
            ->andWhere('w.productVariant = :variant')
            ->andWhere('w.email = :email')
            ->andWhere('w.state = :state')
            ->setParameter('type', $fields['type'])
            ->setParameter('variant', $fields['productVariant'])
            ->setParameter('email', $fields['email'])
            ->setParameter('state', WatchdogState::Active)
            ->setMaxResults(1)
        ;

        if (($fields['id'] ?? null) !== null) {
            $qb
                ->andWhere('w.id != :id')
                ->setParameter('id', $fields['id']);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
