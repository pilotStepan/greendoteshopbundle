<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\PriceCalculator;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class PriceEventListener
{
    public function __construct(
        private priceCalculator $priceCalculator
    ) {}

    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Price) {


            $vat = $entity->getVat();
            $discount = $entity->getDiscount();


            // basePrice
            $priceNoVatNoDiscount = $entity->getPrice();
            // basePrice + vat
            $priceVatNoDiscount = $this->priceCalculator->applyVat($priceNoVatNoDiscount, $vat, VatCalculationType::WithVAT);
            // basePrice + discount
            $priceNoVat = $this->priceCalculator->applyDiscount($discount, $priceNoVatNoDiscount);
            // basePrice + discount + vat
            $priceVat = $this->priceCalculator->applyVat($priceNoVat, $vat, VatCalculationType::WithVAT);;

            $prices[] = [
                'priceNoVat' => $priceNoVat,
                'priceVat' => $priceVat,
                'priceNoVatNoDiscount' => $priceNoVatNoDiscount,
                'priceVatNoDiscount' => $priceVatNoDiscount
            ];

            $entity->setCalculatedPrices($prices);
        }
    }
}
