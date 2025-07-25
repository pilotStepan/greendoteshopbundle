<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Doctrine\ORM\QueryBuilder;

class ParameterDiscountFilter extends AbstractFilter
{
    private const FILTER_LABEL = 'discounts';

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
        if ($property !== self::FILTER_LABEL || !$value) {return;}
        $entityManager = $this->getManagerRegistry()->getManagerForClass(Parameter::class);
        $repository    = $entityManager->getRepository(Parameter::class);
        $repository->getProductParametersByDiscount($queryBuilder);
        //$repository->getByManufacturerGroupAndMostSuperiorCategoryQB($queryBuilder, $value);
    }

    public function getDescription(string $resourceClass): array
    {
        return [self::FILTER_LABEL => [
            'property' => NULL,
            'type' => 'bool',
            'required' => false,
            'description' => 'Filter for products with discount',
            'openapi' => [
                'description' => 'Search across multiple fields',
            ],
        ]];
    }
}
