<?php

namespace Greendot\EshopBundle\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductRepository;

class ProductListener
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        $availability = $this->productRepository->findAvailabilityByProduct($entity);
        $parameters = $this->productRepository->calculateParameters($entity);

        $entity->setAvailability($availability);
        $entity->setParameters($parameters);
    }
}