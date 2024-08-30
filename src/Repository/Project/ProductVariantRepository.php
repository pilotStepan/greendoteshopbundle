<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function findProductVariantByProductIdQB($productId, QueryBuilder $qb): void
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->andWhere(sprintf('%s.product = :productId', $alias))
            ->setParameter('productId', $productId);
    }

    public function findProductVariantByProductParametersQB(array $parameters, QueryBuilder $qb): void
    {
        $alias = $qb->getRootAliases()[0];

        foreach ($parameters as $index => $parameter) {
            $parameterAlias = 'parameter' . $index;
            $qb
                ->leftJoin(sprintf('%s.parameters', $alias), 'p' . $index)
                ->andWhere(sprintf('p%s.data = :%s', $index, $parameterAlias))
                ->setParameter($parameterAlias, $parameter);
        }
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
}
