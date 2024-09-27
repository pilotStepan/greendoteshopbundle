<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\ReviewPoints;
use Doctrine\ORM\QueryBuilder;

class ReviewPointsByReviewFilter extends AbstractFilter
{
    private const FILTER_NAME = 'review';

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

        if ($value) {
            $entityManager = $this->getManagerRegistry()->getManagerForClass(ReviewPoints::class);
            $repository = $entityManager->getRepository(ReviewPoints::class);

            $repository->findReviewPointsQB($value, $queryBuilder);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property' => 'review_id',
                'type' => 'integer',
                'required' => false,
                'description' => 'Filter review points by review ID',
                'openapi' => [
                    'example' => 1,
                    'description' => 'Filter review points by review ID',
                ],
            ],
        ];
    }
}
