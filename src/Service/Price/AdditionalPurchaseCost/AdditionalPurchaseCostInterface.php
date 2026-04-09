<?php

namespace Greendot\EshopBundle\Service\Price\AdditionalPurchaseCost;

use Greendot\EshopBundle\Entity\Project\AdditionalPurchaseCost;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;


#[AutoconfigureTag('greendot.additional_purchase_cost')]
interface AdditionalPurchaseCostInterface
{


    /**
     * @param Purchase $purchase
     * @return AdditionalPurchaseCost|null
     */
    public function getApplicable(Purchase $purchase): ?AdditionalPurchaseCost;
}