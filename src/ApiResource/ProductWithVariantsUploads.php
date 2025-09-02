<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Upload;
use Doctrine\ORM\QueryBuilder;

class ProductWithVariantsUploads extends AbstractFilter
{
    private const FILTER_NAME = 'productWithVariantsUploads';

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

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Upload::class);
        $repository = $entityManager->getRepository(Upload::class);

        $repository->findUploadsForProductQB($value, $queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => "Filter uploads for product and it's variants based on product ID",
                'openapi' => [
                    'description' => "Filter uploads for product and it's variants based on product ID",
                ],
            ]
        ];
    }
}