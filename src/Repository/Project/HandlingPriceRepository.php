<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\AdditionalPurchaseCost;
use Greendot\EshopBundle\Entity\Project\HandlingPrice;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * @extends ServiceEntityRepository<HandlingPrice>
 */
class HandlingPriceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct($registry, HandlingPrice::class);
    }

    // get handling price from a specific transportation or paymentType that is valid by a date
    public function GetByDate(Transportation|PaymentType|AdditionalPurchaseCost $entity, ?DateTime $dateTime = null) : ?HandlingPrice
    {
        // If date is null, make it today
        if ($dateTime === null) {
            $dateTime = new \DateTime();
        }
        $type = match ($this->entityManager->getMetadataFactory()->getMetadataFor(get_class($entity))->getName()){
            AdditionalPurchaseCost::class => 'additionalPurchaseCost',
            Transportation::class => 'transportation',
            PaymentType::class => 'paymentType',
        };

        // Start building the query
        $qb = $this->createQueryBuilder('h');

        // Bind transportation/paymentType id
        $result = $qb
            ->andWhere('h.' . $type . ' = :val')
            ->setParameter('val', $entity->getId())
            // Bind date
            ->andWhere('h.validFrom <= :dateTime')
            ->andWhere(
                $qb->expr()->orX( // if validUntil IS NULL, it's the current HandlingPrice
                    'h.validUntil >= :dateTime',
                    'h.validUntil IS NULL'
                )
            )
            ->setParameter('dateTime', $dateTime->format('Y-m-d H:i:s'))
            // Get result
            ->getQuery()
            ->getResult();

        // Return result
        return $result[0] ?? null;
    }

}
