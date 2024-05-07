<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientDiscount>
 *
 * @method ClientDiscount|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientDiscount|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientDiscount[]    findAll()
 * @method ClientDiscount[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientDiscountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientDiscount::class);
    }

    public function save(ClientDiscount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ClientDiscount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findCurrentClientDiscount(Client $client): ?ClientDiscount
    {
        $qb = $this->createQueryBuilder('cd');
        $now = new \DateTime();

        $qb->andWhere('cd.client = :client')
            ->setParameter('client', $client)
            ->andWhere('cd.dateStart <= :now')
            ->setParameter('now', $now)
            ->andWhere(
                $qb->expr()->orX(
                    'cd.dateEnd >= :now',
                    'cd.dateEnd IS NULL'
                )
            )
            ->setParameter('now', $now)
            ->orderBy('cd.discount', 'DESC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
