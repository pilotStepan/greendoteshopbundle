<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductVariant|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductVariant|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductVariant[]    findAll()
 * @method ProductVariant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    public function findVariantsWithExternalIds(?int $limit = null, ?int$offset = null)
    {
        $qb = $this->createQueryBuilder("pv");

            if ($limit and $offset){
                $qb->setMaxResults($limit)
                ->setFirstResult($offset);
            }


            $qb->andWhere('pv.externalId is not null')
            ->orderBy('pv.product', 'ASC');

            return $qb->getQuery()->getResult();
    }

    public function findLLGVariantIdsWithExternalIds(){
        return $this->createQueryBuilder('pv')
                ->select('pv.id')
                ->leftJoin('pv.product', 'p')
                ->leftJoin('p.producer', 'producer')
                ->andWhere('producer.name = :producerName')->setParameter('producerName', 'LLG')
                ->andWhere('pv.externalId is not null')
                ->getQuery()->getResult();

    }

    public function findAllWithLimit($limit, $offset){
        return $this->createQueryBuilder('pv')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    public function findActiveVariantsForProduct(Product $product){
        return $this->createQueryBuilder('pv')
            ->andWhere('pv.product = :product')->setParameter('product', $product)
            ->andWhere('pv.isActive = 1')
            ->getQuery()->getResult();
    }


    // /**
    //  * @return ProductVariant[] Returns an array of ProductVariant objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ProductVariant
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
