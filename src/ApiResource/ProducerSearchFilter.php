<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Producer;
use Doctrine\ORM\QueryBuilder;

class ProducerSearchFilter extends \ApiPlatform\Doctrine\Orm\Filter\AbstractFilter
{
    protected const CATEGORY_ID_PARAMETER = 'category_id';
    /**
     * @inheritDoc
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property !== self::CATEGORY_ID_PARAMETER) {
            return;
        }

        $entityManager = $this->getManagerRegistry()->getManagerForClass(Producer::class);
        $repository = $entityManager->getRepository(Producer::class);

        $repository->findByCategory($queryBuilder, $value);

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