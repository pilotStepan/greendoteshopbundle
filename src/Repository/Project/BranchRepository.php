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
        $typeId = (int)$type->getId();

        $qb = $this->createQueryBuilder('b');
        $qb->update(Branch::class, 'b')
            ->set('b.is_active', ':inactive')
            ->where('IDENTITY(b.BranchType) = :typeId')
            ->setParameter('inactive', 0)
            ->setParameter('typeId', $typeId)
        ;

        if (!empty($activeProviderIds)) {
            $qb->andWhere($qb->expr()->notIn('b.provider_id', ':activePids'))
                ->setParameter('activePids', array_values(array_unique($activeProviderIds)))
            ;
        }

        return $qb->getQuery()->execute();
    }

    public function findOneByProviderIdAndTypeId(string $providerId, int $typeId): ?Branch
    {
        return $this->createQueryBuilder('b')
            ->where('b.provider_id = :pid')
            ->andWhere('IDENTITY(b.BranchType) = :tid')
            ->setParameter('pid', $providerId)
            ->setParameter('tid', $typeId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
