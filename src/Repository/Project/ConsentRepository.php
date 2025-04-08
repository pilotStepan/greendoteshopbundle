<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Entity\Project\Consent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consent>
 */
class ConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consent::class);
    }

    public function findMissingRequiredConsent(Collection $checkedConsents): ?Consent
    {
        $requiredConsents = $this->findBy(['is_required' => true]);

        foreach ($requiredConsents as $consent) {
            if (!$checkedConsents->contains($consent)) {
                return $consent;
            }
        }

        return null;
    }

    //    /**
    //     * @return Consent[] Returns an array of Consent objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Consent
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
