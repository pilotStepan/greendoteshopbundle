<?php

namespace Greendot\EshopBundle\Service\Price\AdditionalPurchaseCost;

use Greendot\EshopBundle\Entity\Project\AdditionalPurchaseCost;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;


#[AutoconfigureTag('greendot.additional_purchase_cost')]
interface AdditionalPurchaseCostInterface
{

    public function getAdditionalPurchaseCost(): AdditionalPurchaseCost;
    /**
     * @param Purchase $purchase
     * @return bool
     */
    public function isApplicable(Purchase $purchase): bool;

    public function includeFree(): bool;
}