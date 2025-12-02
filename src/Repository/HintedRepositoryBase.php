<?php

namespace Greendot\EshopBundle\Repository;

use Greendot\EshopBundle\I18n\Translatable;
use Gedmo\Translatable\Entity\Translation as GedmoTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

abstract class HintedRepositoryBase extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    final public function findHinted(int $id): ?object
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->setParameter('id', $id);
        $qb = $this->hintQuery($qb->getQuery());
        return $qb->getOneOrNullResult();

    }

    final public function findOneByHinted(array $criteria, array $orderBy = null): ?object
    {
        $qb = $this->createQueryBuilder('e');

        $qb = $this->handleCriteria($qb, $criteria);
        $qb = $this->handleOrderBy($qb, $orderBy);

        $qb = $this->hintQuery($qb->getQuery());
        return $qb->getOneOrNullResult();
    }

    final public function findByHinted(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
    {
        $qb = $this->createQueryBuilder('e');

        $qb = $this->handleCriteria($qb, $criteria);
        $qb = $this->handleOrderBy($qb, $orderBy);

        if ($limit) $qb->setMaxResults($limit);

        if ($offset) $qb->setFirstResult($offset);

        $qb = $this->hintQuery($qb->getQuery());
        return $qb->getResult();
    }

    final public function findAllHinted(): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb = $this->hintQuery($qb->getQuery());
        return $qb->getResult();
    }

    public function findPropertyInLocale(object $entity,string $property, string $targetLocale): ?string
    {
        if ($entity instanceof Translatable && $entity->getTranslatableLocale() === $targetLocale) {
            return $entity->getSlug();
        }

        $em = $this->getEntityManager();
        $repository = $em->getRepository(GedmoTranslation::class);

        $translations = $repository->findTranslations($entity);

        if (isset($translations[$targetLocale][$property])) {
            return $translations[$targetLocale][$property];
        }

        return $this->createQueryBuilder('e')
            ->select("e.$property")
            ->where('e.id = :id')
            ->setParameter('id', $entity->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function handleCriteria(QueryBuilder $qb, array $criteria): QueryBuilder
    {
        foreach ($criteria as $key => $value){
            $name = 'param_' . $key . uniqid();
            $qb->andWhere('e.' . $key . ' = :'. $name);
            $qb->setParameter($name, $value);
        }
        return $qb;
    }
    private function handleOrderBy(QueryBuilder $qb, ?array $orderArray): QueryBuilder
    {
        if (!$orderArray) return $qb;

        foreach ($orderArray as $key => $value){
            $qb->addOrderBy('e.' . $key, $value);
        }
        return $qb;
    }


    final public function hintQuery(Query $query): Query
    {
        return $query->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
        );
    }


}
