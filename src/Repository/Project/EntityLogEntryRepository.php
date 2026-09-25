<?php

namespace Greendot\EshopBundle\Repository\Project;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;
use Greendot\EshopBundle\Entity\Project\EntityLogEntry;

/**
 * @template-extends LogEntryRepository<EntityLogEntry>
 */
class EntityLogEntryRepository extends LogEntryRepository
{
    /**
     * All log rows for the given object, across every locale, newest first.
     *
     * @param class-string $objectClass
     * @return EntityLogEntry[]
     */
    public function findForObject(string $objectClass, int|string $objectId): array
    {
        return $this->createQueryBuilder('log')
            ->andWhere('log.objectId = :objectId')
            ->andWhere('log.objectClass = :objectClass')
            ->orderBy('log.version', 'DESC')
            ->setParameter('objectId', (string) $objectId)
            ->setParameter('objectClass', $objectClass)
            ->getQuery()
            ->getResult();
    }

    /**
     * Log rows up to and including $version, newest first.
     *
     * @param class-string $objectClass
     * @return EntityLogEntry[]
     */
    public function findUpToVersion(string $objectClass, int|string $objectId, int $version): array
    {
        return $this->createQueryBuilder('log')
            ->andWhere('log.objectId = :objectId')
            ->andWhere('log.objectClass = :objectClass')
            ->andWhere('log.version <= :version')
            ->orderBy('log.version', 'DESC')
            ->setParameter('objectId', (string) $objectId)
            ->setParameter('objectClass', $objectClass)
            ->setParameter('version', $version)
            ->getQuery()
            ->getResult();
    }

    /**
     * All rows sharing one batch id (one "commit" - possibly spanning several locales), oldest first.
     *
     * @return EntityLogEntry[]
     */
    public function findByBatchId(string $batchId): array
    {
        return $this->createQueryBuilder('log')
            ->andWhere('log.batchId = :batchId')
            ->orderBy('log.version', 'ASC')
            ->setParameter('batchId', $batchId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Every row ever logged for this (object, locale) combination.
     *
     * @param class-string $objectClass
     * @return EntityLogEntry[]
     */
    public function findForObjectLocale(string $objectClass, int|string $objectId, string $locale): array
    {
        return $this->createQueryBuilder('log')
            ->andWhere('log.objectId = :objectId')
            ->andWhere('log.objectClass = :objectClass')
            ->andWhere('log.locale = :locale')
            ->setParameter('objectId', (string) $objectId)
            ->setParameter('objectClass', $objectClass)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * Version number the next log row for this object should carry (mirrors Gedmo's
     * LoggableAdapter::getNewVersion()); for rows EntityVersionLogger builds by hand.
     *
     * @param class-string $objectClass
     * @param int|string $objectId
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function nextVersion(string $objectClass, int|string $objectId): int
    {
        $max = $this->createQueryBuilder('log')
            ->select('MAX(log.version)')
            ->andWhere('log.objectId = :objectId')
            ->andWhere('log.objectClass = :objectClass')
            ->setParameter('objectId', (string) $objectId)
            ->setParameter('objectClass', $objectClass)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
