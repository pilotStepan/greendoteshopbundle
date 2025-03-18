<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Upload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Upload>
 *
 * @method Upload|null find($id, $lockMode = null, $lockVersion = null)
 * @method Upload|null findOneBy(array $criteria, array $orderBy = null)
 * @method Upload[]    findAll()
 * @method Upload[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private UploadGroupRepository $uploadGroupRepository)
    {
        parent::__construct($registry, Upload::class);
    }

    public function save(Upload $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Upload $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findUploadsForProductQB(int $productId, QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->innerJoin($alias . '.uploadGroup', 'ug')
            ->leftJoin('ug.productUploadGroup', 'pug')
            ->leftJoin('pug.Product', 'p')
            // ->leftJoin('ug.productVariantUploadGroups', 'pvug')
            // ->leftJoin('pvug.ProductVariant', 'pv')
            // ->leftJoin('pv.product', 'pvp')
            ->andWhere('p.id = :productId')
            ->setParameter('productId', $productId);

        return $qb;
    }
    public function findUploadsForProductVariantQB(int $variantId, QueryBuilder $qb): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0];

        $qb
            ->innerJoin($alias . '.uploadGroup', 'ug')
            ->leftJoin('ug.productVariantUploadGroups', 'pvug')
            ->leftJoin('pvug.ProductVariant', 'pv')
            ->andWhere('pv.id = :variantId')
            ->setParameter('variantId', $variantId);

        return $qb;
    }

    public function getUploadForProduct(Product $product)
    {
        $uploadGroups = $this->uploadGroupRepository->getAllUploadGroupsForProduct($product);

        return $this->createQueryBuilder('u')
            //must be distinct path, because the llg api has the same image for multiple variants, so it doesn't look weird when printing the result, also helps avoid printing unwanted duplicates
            ->select('DISTINCT u.path')
            ->addSelect('u.name')
            ->andWhere('u.uploadGroup in (:uploadGroups)')->setParameter('uploadGroups', $uploadGroups)
            ->getQuery()->getResult();
    }
}
