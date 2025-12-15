<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Currency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Currency>
 *
 * @method Currency|null find($id, $lockMode = null, $lockVersion = null)
 * @method Currency|null findOneBy(array $criteria, array $orderBy = null)
 * @method Currency[]    findAll()
 * @method Currency[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CurrencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Currency::class);
    }

    public function findCurrencyByLocale(string $locale): ?Currency
    {
        return $this->createQueryBuilder('currency')
            ->andWhere('currency.defaultLocale = :locale')
            ->setParameter('locale', $locale)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
