<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Watchdog;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogType;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogState;
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
}
