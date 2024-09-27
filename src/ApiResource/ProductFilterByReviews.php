<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductFilterByReviews extends AbstractFilter
{
    private const PRODUCT_LABEL = 'productFilterByReviews';

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

        $booleanValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if (!$booleanValue) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
        $repository    = $entityManager->getRepository(Product::class);

        $repository->findByReviewsQB($queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PRODUCT_LABEL => [
                'property'    => null,
                'type'        => 'string',
                'required'    => false,
                'description' => 'Filters products based on reviews.',
                'openapi'     => [
                    'description' => 'Filters products based on reviews.',
                ],
            ]
        ];
    }
}