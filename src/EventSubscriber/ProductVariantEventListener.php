<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ProductVariantEventListener
{
    public function __construct(
    ) {}

    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

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
