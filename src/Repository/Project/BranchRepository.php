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

    public function findOneByProviderId(string $providerId): ?Branch
    {
        return $this->createQueryBuilder('b')
            ->where('b.provider_id = :pid')
            ->setParameter('pid', $providerId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param string[] $providerIds
     * @return array<string, Branch> indexed by provider_id
     */
    public function findIndexedByProviderIds(array $providerIds): array
    {
        if (empty($providerIds)) return [];

        $branches = $this->createQueryBuilder('b')
            ->where('b.provider_id IN (:pids)')
            ->setParameter('pids', array_values(array_unique($providerIds)))
            ->getQuery()
            ->getResult()
        ;

        $indexed = [];
        foreach ($branches as $branch) {
            $indexed[$branch->getProviderId()] = $branch;
        }

        return $indexed;
    }

    /**
     * Lightweight list of all selectable branches of a transportation group.
     * Doctrine filters don't apply to DBAL, so active/enabled flags are checked explicitly.
     *
     * @return list<array{0: int, 1: float, 2: float, 3: int, 4: int, 5: string, 6: string, 7: string, 8: string}>
     *         [id, lat, lng, branchTypeId, transportationId, name, street, city, zip]
     */
    public function findCompactForMap(int $groupId, string $country): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllNumeric(
            'SELECT b.id, b.lat, b.lng, b.branch_type_id, b.transportation_id, b.name, b.street, b.city, b.zip
             FROM branch b
             INNER JOIN transportation t ON t.id = b.transportation_id
             INNER JOIN transportation_transportation_group tg ON tg.transportation_id = t.id
             WHERE tg.transportation_group_id = :groupId
               AND t.is_enabled = 1
               AND b.is_active = 1
               AND b.country = :country
               AND b.branch_type_id IS NOT NULL
               AND b.lat IS NOT NULL
               AND b.lng IS NOT NULL
             ORDER BY b.id',
            ['groupId' => $groupId, 'country' => $country],
        );

        foreach ($rows as &$row) {
            $row[0] = (int)$row[0];
            $row[1] = (float)$row[1];
            $row[2] = (float)$row[2];
            $row[3] = (int)$row[3];
            $row[4] = (int)$row[4];
            $row[5] = (string)$row[5];
            $row[6] = (string)$row[6];
            $row[7] = (string)$row[7];
            $row[8] = (string)$row[8];
        }
        unset($row);

        return $rows;
    }
}
