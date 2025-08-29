<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Upload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\UploadGroup;
use Greendot\EshopBundle\Entity\Project\UploadType;

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

    /**
     * @param Category|int $category
     * @param int|null $uploadGroupType
     * @return Upload[]
     */
    public function getCategoryUploads(Category|int $category, ?int $uploadGroupType = null, UploadType|int|null $uploadType = null, bool $excludeThumbnail = true): array
    {
        if (!$category instanceof Category) {
            $category = $this->getEntityManager()->getRepository(Category::class)->find($category);
        }
        $categoryUploadGroups = $this->getEntityManager()->getRepository(UploadGroup::class)->getCategoryUploadGroup($category, $uploadGroupType);
        if (!$categoryUploadGroups or count($categoryUploadGroups) < 1) {
            return [];
        }

        if ($uploadType and $uploadType instanceof UploadType){
            $uploadType = $uploadType->getId();
        }


        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.uploadGroup in (:category_upload_groups)')
            ->setParameter('category_upload_groups', $categoryUploadGroups);

        if ($excludeThumbnail and $category?->getUpload()?->getPath()) {
            $qb->andWhere('u.id != :upload')
                ->setParameter('upload', $category->getUpload()->getId());
        }

        if ($uploadType){
            $qb->andWhere('u.uploadType = :upload_type')
                ->setParameter('upload_type', $uploadType);
        }

        return $qb->getQuery()->getResult();
    }

    public function getProductUploads(Product $product, bool $includeVariants = false, ?int $uploadGroupType = 0): array
    {
        $uploadGroups = $this->getEntityManager()->getRepository(UploadGroup::class)->getAllUploadGroupsForProduct($product, $includeVariants, $uploadGroupType);
        $qb = $this->createQueryBuilder('u');
        if ($product?->getUpload()?->getId()) {
            $qb->andWhere('u.id != :thumbnail')
                ->setParameter('thumbnail', $product->getUpload()->getId());
        }
        $qb->andWhere('u.uploadGroup in (:uploadGroups)')
            ->setParameter('uploadGroups', $uploadGroups);
        return $qb->getQuery()->getResult();
    }
}
