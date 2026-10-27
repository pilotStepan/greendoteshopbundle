<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Review;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProductReviews extends AbstractFilter
{
    private const FILTER_NAME = 'productReviews';

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

        if (!is_scalar($value) || !ctype_digit((string)$value)) {
            throw new BadRequestHttpException(sprintf('Filter "%s" must be a product ID.', self::FILTER_NAME));
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Review::class);
        $repository = $entityManager->getRepository(Review::class);

        $repository->findByProductQB((int)$value, $queryBuilder);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::FILTER_NAME => [
                'property' => null,
                'type' => 'int',
                'required' => false,
                'description' => 'Filters reviews based on product ID.',
                'openapi' => [
                    'description' => 'Filters reviews based on product ID.',
                ],
            ]
        ];
    }
}