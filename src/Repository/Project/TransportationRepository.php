<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Transportation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transportation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transportation[]    findAll()
 * @method Transportation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transportation::class);
    }

    public function findOneByLowFree(?string $country = null): ?Transportation
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('t');
        $qb->select('t')
            ->innerJoin('t.handlingPrices', 'h')
            ->andWhere('h.free_from_price >= 0')
            ->andWhere('h.validFrom <= :now')
            ->andWhere(
                $qb->expr()->orX(
                    'h.validUntil >= :now',
                    'h.validUntil IS NULL',
                ),
            )
            ->setParameter('now', $now)
            ->orderBy('h.free_from_price', 'ASC')
            ->setMaxResults(1)
        ;

        if ($country !== null) {
            $qb->andWhere('t.country = :country')
                ->setParameter('country', $country)
            ;
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
