<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;

class FilteredParametersStateProvider implements ProviderInterface
{
    public function __construct(
        private ParameterRepository     $parameterRepository,
        private EntityManagerInterface  $em,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $qb = $this->parameterRepository->createQueryBuilder('p');
        $qb->select('p');

        $filters = $context['filters']; 
        if (!empty($filters['category_id'])) {
            $this->parameterRepository->getProductParametersByTopCategory($qb, $filters['category_id']);
        }
        if (!empty($filters['supplier_id'])) {
            $this->parameterRepository->getProductParametersByProducer($qb, $filters['supplier_id']);
        }
        if (!empty($filters['discounts'])) {
            $this->parameterRepository->getProductParametersByDiscount($qb);
        }

        // add color names
        $qb ->leftJoin('Greendot\EshopBundle\Entity\Project\Colour', 'c', 'WITH', 'c.hex = p.data') // <- join color      
            ->addSelect('c.name as colorName');
        $results = $qb->getQuery()->getResult();
        foreach ($results as $row) {
            $parameter = $row[0];
            $this->em->detach($parameter);
            $parameter->setColorName($row['colorName']);
        }

        return array_map(fn($row) => $row[0], $results);
    }

}