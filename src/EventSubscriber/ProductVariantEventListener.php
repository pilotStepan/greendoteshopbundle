<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: ProductVariant::class)]
class ProductVariantEventListener
{
    public function __construct(
        private readonly CalculatedPricesService $calculatedPricesService
    ) {}

    public function postLoad(ProductVariant $productVariant, PostLoadEventArgs $event): void
    {
        $this->calculatedPricesService->makeCalculatedPricesForProductVariant($productVariant);
    }
}
