<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Entity\Project\Category;

class CategoryHasProductsFilter extends AbstractFilter
{

    const PROPERTY = 'has_products';

    /**
     * @inheritDoc
     */
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($property !== self::PROPERTY or $resourceClass !== Category::class or $value != "true") return;

        $alias = $queryBuilder->getAllAliases()[0];
        $categoryProductJoin = $queryNameGenerator->generateJoinAlias('categoryProducts');
        $queryBuilder
            ->innerJoin("$alias.categoryProducts", $categoryProductJoin);
    }

    /**
     * @inheritDoc
     */
    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) return [];

        $description = [];
        foreach ($this->properties as $property => $strategy){
            $description['category_has_products'.$property] = [
                'property' => $property,
                'type' => 'bool',
                'required' => false,
                'description' => 'Returns only categories that have some related products',
                'openapi' => new Parameter(
                    name: $property,
                    in: 'query',
                    allowEmptyValue: true,
                    explode: false,
                )
            ];
        }

        return $description;
    }
}