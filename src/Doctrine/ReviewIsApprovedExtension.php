<?php

namespace Greendot\EshopBundle\Doctrine;

use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Review;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;

#[AsTaggedItem('api_platform.doctrine.orm.query_extension.collection')]
#[AsTaggedItem('api_platform.doctrine.orm.query_extension.item')]
final class ReviewIsApprovedExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addIsApprovedCondition($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addIsApprovedCondition($queryBuilder, $resourceClass);
    }

    private function addIsApprovedCondition(QueryBuilder $qb, string $resourceClass): void
    {
        if ($resourceClass !== Review::class) return;

        $rootAlias = $qb->getRootAliases()[0];
        $qb->andWhere(sprintf('%s.is_approved = :isApproved', $rootAlias))
            ->setParameter('isApproved', true)
        ;
    }
}