<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Upload;
use Doctrine\ORM\QueryBuilder;

class ProductVariantUploads extends AbstractFilter
{
    private const FILTER_NAME = 'productVariantUploads';

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

        $repository->findUploadsForProductVariantQB($value, $queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Filter uploads based on product variant ID',
                'openapi' => [
                    'description' => 'Filter uploads based on product variant ID',
                ],
            ]
        ];
    }
}