<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductPriceSortFilter extends AbstractFilter
{
    protected const PRICE_SORT_PARAMETER = "price_sort";

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
        if ($property !== self::PRICE_SORT_PARAMETER) {
            return;
        }

        if (strtolower($value) === "desc" or strtolower($value) === "asc") {
            $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
            $repository    = $entityManager->getRepository(Product::class);
            $repository->sortProductsByPrice($queryBuilder, new \DateTime(), $value);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRICE_SORT_PARAMETER => [
                'property' => 'price',
                'type'     => 'string',
                'required' => false,
                'swagger'  => [
                    'description' => 'Sort by price',
                ],
            ],
        ];
    }
}

