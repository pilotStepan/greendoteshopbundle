<?php

namespace App\Repository\Project;

use App\Entity\Project\HandlingPrice;
use App\Entity\Project\PaymentType;
use App\Entity\Project\Transportation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * @extends ServiceEntityRepository<HandlingPrice>
 */
class HandlingPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HandlingPrice::class);
    }

    // get handling price from a specific transportation or paymentType that is valid by a date
    public function GetByDate(Transportation|PaymentType $transportOrPayment, ?DateTime $dateTime = null) : ?HandlingPrice
    {
        // If date is null, make it today
        if ($dateTime === null) {
            $dateTime = new \DateTime();
        }

        // Determine type
        $type = $transportOrPayment instanceof Transportation ? 'transportation' : 'paymentType';

        // Start building the query
        $qb = $this->createQueryBuilder('h');

        // Bind transportation/paymentType id
        $result = $qb
            ->andWhere('h.' . $type . ' = :val')
            ->setParameter('val', $transportOrPayment->getId())
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

    //    /**
    //     * @return HandlingPrice[] Returns an array of HandlingPrice objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?HandlingPrice
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
