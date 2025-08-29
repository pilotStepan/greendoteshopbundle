<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\UploadGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UploadGroup>
 *
 * @method UploadGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method UploadGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method UploadGroup[]    findAll()
 * @method UploadGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UploadGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadGroup::class);
    }

    public function save(UploadGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UploadGroup $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getAllUploadGroupsForProduct(Product $product, $accountVariants = true): array
    {
        $productUploadGroups = $this->createQueryBuilder('ug')
            ->leftJoin('ug.productUploadGroup', 'pug')
            ->andWhere('pug.Product = :product')->setParameter('product', $product)
            ->getQuery()->getResult();
        if ($accountVariants){
            $variantUploadGroups = $this->createQueryBuilder('ug')
                ->leftJoin('ug.productVariantUploadGroups', 'pvug')
                ->andWhere('pvug.ProductVariant in (:variants)')->setParameter('variants', $product->getProductVariants())
                ->getQuery()->getResult();
            return array_merge($productUploadGroups, $variantUploadGroups);
        }

        return  $productUploadGroups;

    }

    /**
     * @param Category|int $category
     * @param null|int $type
     * @return UploadGroup[]
     */
    public function getCategoryUploadGroup(Category|int $category, ?int $type = null):array
    {
        if ($category instanceof Category){
            $category = $category->getId();
        }

        $qb =  $this->createQueryBuilder('upload_group')
            ->leftJoin('upload_group.categoryUploadGroups', 'cup')
            ->andWhere('cup.Category = :category')
            ->setParameter('category', $category);

        if ($type !== null){
            $qb->andWhere('upload_group.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function getPersonUploadGroup(Person|int $person, ?int $type = null): array
    {
        if($person instanceof Person){
            $person = $person->getId();
        }

        $qb = $this->createQueryBuilder('upload_group')
            ->leftJoin('upload_group.personUploadGroups', 'person_upload_groups')
            ->andWhere('person_upload_groups.Person = :person')
            ->setParameter('person', $person);

        if ($type !== null){
            $qb->andWhere('upload_group.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }
}
