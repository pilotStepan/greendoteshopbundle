<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Entity\Project\Consent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Purchase;

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

    /**
     * @param Purchase $purchase
     * @return int[]
     */
    public function getIdsForPurchase(Purchase $purchase): array
    {
        $ids = $this->createQueryBuilder('consent')
            ->select('consent.id')
            ->andWhere('consent.purchase = :purchase')
            ->setParameter('purchase', $purchase->getId())
            ->getQuery()->getArrayResult();

        return array_column($ids, 'id');
    }
}
