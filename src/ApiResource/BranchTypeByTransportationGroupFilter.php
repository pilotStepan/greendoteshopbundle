<?php

namespace Greendot\EshopBundle\ApiResource;

use Doctrine\ORM\QueryBuilder;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\TypeInfo\TypeIdentifier;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;

final class BranchTypeByTransportationGroupFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($property !== 'transportationGroup.id' || $value === null || $value === '') {
            return;
        }

        $ids = [];
        if (is_array($value)) {
            $candidates = $value;
        } else {
            $candidates = explode(',', (string)$value);
        }

        foreach ($candidates as $v) {
            $v = trim((string)$v);
            if ($v === '' || !ctype_digit($v)) {
                continue;
            }
            $n = (int)$v;
            if ($n > 0) {
                $ids[] = $n;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $branchAlias = $queryNameGenerator->generateJoinAlias('branch');
        $transpAlias = $queryNameGenerator->generateJoinAlias('transportation');
        $groupAlias = $queryNameGenerator->generateJoinAlias('group');
        $paramName = $queryNameGenerator->generateParameterName('groupIds');

        $queryBuilder->innerJoin(sprintf('%s.Branch', $rootAlias), $branchAlias)
            ->innerJoin(sprintf('%s.transportation', $branchAlias), $transpAlias)
            ->innerJoin(sprintf('%s.groups', $transpAlias), $groupAlias)
            ->andWhere(sprintf('%s.id IN (:%s)', $groupAlias, $paramName))
            ->setParameter($paramName, $ids)
            ->distinct()
        ;
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'transportationGroup.id' => [
                'property' => null,
                'type' => TypeIdentifier::INT,
                'required' => false,
                'openapi' => [
                    'description' =>
                        'Filter BranchTypes by TransportationGroup id. ' .
                        'Use ?transportationGroup.id=3 or ?transportationGroup.id[]=3&transportationGroup.id[]=4',
                    'name' => 'transportationGroup.id',
                    'schema' => [
                        'oneOf' => [
                            ['type' => 'integer'],
                            ['type' => 'array', 'items' => ['type' => 'integer']],
                        ],
                    ],
                ],
            ],
        ];
    }
}