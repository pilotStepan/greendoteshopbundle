<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductLabel extends AbstractFilter
{
    private const PRODUCT_LABEL = 'productLabel';

    protected function filterProperty(
        string                      $property,
                                    $value,
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        Operation                   $operation = null,
        array                       $context = []
    ): void
    {
        if ($property !== self::PRODUCT_LABEL) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
        $repository    = $entityManager->getRepository(Product::class);

        $repository->findByLabelQB($value, $queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRODUCT_LABEL => [
                'property'    => null,
                'type'        => 'string',
                'required'    => false,
                'description' => 'Filters products based on the specified label.',
                'openapi'     => [
                    'description' => 'Filters products based on the specified label.',
                ],
            ]
        ];
    }
}