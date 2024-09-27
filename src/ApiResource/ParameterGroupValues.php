<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Doctrine\ORM\QueryBuilder;

class ParameterGroupValues extends AbstractFilter
{
    private const PRODUCT_LABEL = 'parameterGroupValues';

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

        $entityManager = $this->getManagerRegistry()->getManagerForClass(ParameterGroup::class);
        $repository = $entityManager->getRepository(ParameterGroup::class);

        $repository->findParametersQB($queryBuilder, $value);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRODUCT_LABEL => [
                'property' => null,
                'type' => 'integer',
                'required' => false,
                'description' => 'Filters parameters based on parameter group id.',
                'openapi' => [
                    'description' => 'Filters parameters based on parameter group id.',
                ],
            ]
        ];
    }
}