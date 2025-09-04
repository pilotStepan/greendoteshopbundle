<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Service\CategoryInfoGetter;

class ProducerSearchFilter extends AbstractFilter
{

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly CategoryInfoGetter $categoryInfoGetter
    )
    {
        parent::__construct($managerRegistry);
    }

    protected const CATEGORY_ID_PARAMETER = 'category_id';
    /**
     * @inheritDoc
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property !== self::CATEGORY_ID_PARAMETER) {
            return;
        }
        $category = $this->getManagerRegistry()->getRepository(Category::class)->find($value);
        if (!$category){
            throw new \Exception('Category with ID: '.$value. ' does not exist!');
        }
        $categories = $this->categoryInfoGetter->getAllSubCategories($category, true);

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Producer::class);
        $repository = $entityManager->getRepository(Producer::class);

        $repository->findByCategory($queryBuilder, $categories);

    }

    /**
     * @inheritDoc
     */
    public function getDescription(string $resourceClass): array
    {


        return  ["category_id" => [
                'property' => NULL,
                'type' => 'int',
                'required' => false,
                'description' => 'Filter using category!',
                'openapi' => [
                    'description' => 'Search across multiple fields',
                ],
            ]];


    }
}