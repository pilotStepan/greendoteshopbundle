<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Entity\Project\BranchType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Branch>
 */
class BranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }

    public function deactivateMissingByType(BranchType $type, array $activeProviderIds): int
    {
        $qb = $this->createQueryBuilder('b');

        $qb->update(Branch::class, 'b')
            ->set('b.is_active', ':inactive')
            ->where('b.BranchType = :type')
            ->setParameter('inactive', 0)
            ->setParameter('type', $type)
        ;

        if (!empty($activeProviderIds)) {
            $qb->andWhere($qb->expr()->notIn('b.provider_id', ':activePids'))
                ->setParameter('activePids', array_values(array_unique($activeProviderIds)))
            ;
        }

        return $qb->getQuery()->execute();
    }
}
