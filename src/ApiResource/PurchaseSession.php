<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Doctrine\ORM\QueryBuilder;

class PurchaseSession extends AbstractFilter
{
    private const FILTER_NAME = 'purchaseSession';

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
        if ($property !== self::FILTER_NAME) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Purchase::class);
        $repository = $entityManager->getRepository(Purchase::class);

        $repository->findBySession($queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property' => null,
                'type' => 'int',
                'required' => false,
                'description' => 'Filters purchase based on session.',
                'openapi' => [
                    'description' => 'Filters purchase based on session.',
                ],
            ]
        ];
    }
}