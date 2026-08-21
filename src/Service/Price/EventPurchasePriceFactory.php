<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;

class EventPurchasePriceFactory
{
    public function __construct(
        private readonly EventPriceFactory $eventPriceFactory,
        private readonly PriceUtils        $priceUtils,
    )
    {
    }

    public function create(
        Purchase                $purchase,
        Currency                $currency,
        VatCalculationType      $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount,
    ): EventPurchasePrice
    {
        if ($vatCalculationType === VatCalculationType::WithVAT && $purchase->isVatExempted()){
            $vatCalculationType = VatCalculationType::WithoutVAT;
        }

        $conversionRate = $this->priceUtils->getConversionRate($currency, $purchase);

        return new EventPurchasePrice(
            $purchase,
            $vatCalculationType,
            $discountCalculationType,
            $currency,
            $conversionRate,
            $this->eventPriceFactory,
            $this->priceUtils
        );
    }
}
