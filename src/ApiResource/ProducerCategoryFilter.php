<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class ProducerCategoryFilter extends AbstractFilter
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        ManagerRegistry $managerRegistry = null,
        LoggerInterface $logger = null,
        array $properties = null,
        NameConverterInterface $nameConverter = null,
    )
    {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    const PROPERTY = 'category_id';
    /**
     * @inheritDoc
     */
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($property !== self::PROPERTY or $resourceClass !== Producer::class) return;

        $categories = $this->categoryRepository->findAllChildCategoryIds($value);

        $productJoin = $queryNameGenerator->generateJoinAlias('product');
        $categoryProductsJoin = $queryNameGenerator->generateJoinAlias('categoryProducts');

        $categoriesParameter = $queryNameGenerator->generateParameterName('categories');

        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder
            ->leftJoin("$alias.Product", $productJoin)
            ->leftJoin("$productJoin.categoryProducts", $categoryProductsJoin)
            ->andWhere("$categoryProductsJoin.category in (:$categoriesParameter)")
            ->setParameter($categoriesParameter, $categories);

    }

    /**
     * @inheritDoc
     */
    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) return [];

        $description = [];
        foreach ($this->properties as $property => $strategy){
            $description['producer_'.$property] = [
                'property' => $property,
                'type' => 'int',
                'required' => false,
                'description' => 'Filters producers by products in and under given category.',
                'openapi' => new Parameter(
                    name: $property,
                    in: 'query',
                    allowEmptyValue: true,
                    explode: false,
                )
            ];
        }

        return $description;
    }
}