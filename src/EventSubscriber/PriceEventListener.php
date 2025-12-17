<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\PriceCalculator;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Service\ListenerManager;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Price::class)]
class PriceEventListener
{
    public function __construct(
        private priceCalculator $priceCalculator,
        private ListenerManager $listenerManager,
    ) {}

    public function postLoad(Price $price, PostLoadEventArgs $args): void
    {
        $entity = $price;

        if (!$this->supports($entity)) {
            return;
        }


        $vat = $entity->getVat();
        $discount = $entity->getDiscount();


        // TODO: use calculated prices service instaed
        // basePrice
        $priceNoVatNoDiscount = $entity->getPrice();
        // basePrice + vat
        $priceVatNoDiscount = $this->priceCalculator->applyVat($priceNoVatNoDiscount, $vat, VatCalculationType::WithVAT);
        // basePrice + discount
        $priceNoVat = $this->priceCalculator->applyDiscount($discount, $priceNoVatNoDiscount);
        // basePrice + discount + vat
        $priceVat = $this->priceCalculator->applyVat($priceNoVat, $vat, VatCalculationType::WithVAT);;

        $prices = [
            'priceNoVat' => $priceNoVat,
            'priceVat' => $priceVat,
            'priceNoVatNoDiscount' => $priceNoVatNoDiscount,
            'priceVatNoDiscount' => $priceVatNoDiscount
        ];

        $entity->setCalculatedPrices($prices);
    
    }

    public function supports($entity) : bool
    {
        return $entity instanceof Price && !$this->listenerManager->isDisabled(self::class);
    }
}
