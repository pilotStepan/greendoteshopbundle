<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductFromAllSubCategories extends AbstractFilter
{
    private const PRODUCT_LABEL = 'categoryId';

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property !== self::PRODUCT_LABEL || $value === null) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
        $repository    = $entityManager->getRepository(Product::class);

        $repository->findCategoryProductsQB($value, $queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [self::PRODUCT_LABEL => [
            'property'    => NULL,
            'type'        => 'int',
            'required'    => false,
            'description' => 'Finds products in given category and all the sub categories.',
            'openapi'     => [
                'description' => 'Finds products in given category and all the sub categories.',
            ],
        ]];
    }
}