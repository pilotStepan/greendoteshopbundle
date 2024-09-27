<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\ORM\QueryBuilder;

class ProductVariantFilter extends AbstractFilter
{
    private const PRODUCT_ID = 'productId';
    private const PRODUCT_PARAMETER = 'productParameter';

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
        if (!in_array($property, [self::PRODUCT_ID, self::PRODUCT_PARAMETER])) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(ProductVariant::class);
        $repository = $entityManager->getRepository(ProductVariant::class);

        if ($property === self::PRODUCT_ID) {
            $repository->findProductVariantByProductIdQB($value, $queryBuilder);
        }

        if ($property === self::PRODUCT_PARAMETER) {
            $repository->findProductVariantByProductParametersQB($value, $queryBuilder);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRODUCT_ID => [
                'property' => 'productId',
                'type' => 'int',
                'required' => false,
                'description' => 'Filter by product ID',
                'openapi' => [
                    'description' => 'Filter by product ID',
                ],
            ],
            self::PRODUCT_PARAMETER => [
                'property' => 'productParameter',
                'type' => 'array',
                'required' => false,
                'description' => 'Filter by product parameters',
                'openapi' => [
                    'description' => 'Filter by product parameters',
                ],
            ],
        ];
    }
}
