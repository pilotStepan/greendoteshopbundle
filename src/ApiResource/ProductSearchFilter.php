<?php

namespace Greendot\EshopBundle\ApiResource;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Entity\Project\Product;
use Doctrine\ORM\QueryBuilder;

class ProductSearchFilter extends \ApiPlatform\Doctrine\Orm\Filter\AbstractFilter
{
    protected const IN_SALE_PARAMETER = 'in_sale';
    /**
     * @inheritDoc
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property !== self::IN_SALE_PARAMETER) {
            return;
        }
        if ($value != 'true'){
            return;
        }
        $entityManager = $this->getManagerRegistry()->getManagerForClass(Product::class);
        $repository = $entityManager->getRepository(Product::class);

        $repository->findProductsWithDiscountForAPI($queryBuilder, new \DateTime());

    }

    /**
     * @inheritDoc
     */
    public function getDescription(string $resourceClass): array
    {


        return  ["in_sale" => [
                'property' => NULL,
                'type' => 'bool',
                'required' => false,
                'description' => 'Find products in sale!',
                'openapi' => [
                    'description' => 'Find products in sale',
                ],
            ]];


    }
}