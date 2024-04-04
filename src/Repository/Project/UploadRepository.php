<?php

namespace Greendot\EshopBundle\Repository\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Upload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function getProductEntityRelatedUploads(Product $product)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.uploadGroup in (:uploadGroups)')->setParameter('uploadGroups', $product->getProductUploadGroups())
            ->getQuery()->getResult();
    }

//    /**
//     * @return Upload[] Returns an array of Upload objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Upload
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
