<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: ProductVariant::class)]
class ProductVariantEventListener
{
    public function __construct(
    ) {}

    public function postLoad(ProductVariant $productVariant, PostLoadEventArgs $event): void
    {
        $entity = $productVariant;

        if ($entity instanceof ProductVariant) {

            $prices = $entity->getPrice();

            // filter prices to current date
            $now = new \DateTimeImmutable();
            $activePrices = [];
            foreach ($prices as $price){
                $validFrom = $price->getValidFrom();
                $validUntil = $price->getValidUntil();

                if (($validFrom === null || $validFrom <= $now) &&
                    ($validUntil === null || $validUntil >= $now)){
                    $activePrices[] = $price;
                };
            }


            // get the lowest minimalAmount price
            $lowestPrice = null;
            foreach ($activePrices as $price) {
                if ($lowestPrice === null || $price->getMinimalAmount() < $lowestPrice->getMinimalAmount()) {
                    $lowestPrice = $price;
                }
            }

            // set calculated prices
            $entity->setCalculatedPrices($lowestPrice->getCalculatedPrices());
        }
    }
}
