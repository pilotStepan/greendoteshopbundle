<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductAvailability extends AbstractFilter
{
    private const PRODUCT_LABEL = 'productAvailability';

    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void
    {
        if ($property !== self::PRODUCT_LABEL) {
            return;
        }

        $booleanValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if (!$booleanValue) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
        $repository = $entityManager->getRepository(Product::class);

        $repository->findByAvailabilityQB($queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRODUCT_LABEL => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Filters products based on whether at least one product variant is available and product state is published.',
                'openapi' => [
                    'description' => 'Filters products based on whether at least one product variant is available and product state is published.',
                ],
            ]
        ];
    }
}