<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductParameterSearch extends AbstractFilter
{
    private const FILTER_NAME = 'productParameter';

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
        if ($property !== self::FILTER_NAME) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
        $repository    = $entityManager->getRepository(Product::class);

        $repository->productsByParameterQB($queryBuilder, $value);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property'    => NULL,
                'type'        => 'string',
                'required'    => false,
                'description' => 'Filter using parameter',
                'openapi'     => [
                    'description' => 'Filter using parameter',
                    'explode'     => true
                ],
            ]
        ];
    }
}