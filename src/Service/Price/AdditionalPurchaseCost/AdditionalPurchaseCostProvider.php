<?php

namespace Greendot\EshopBundle\Service\Price\AdditionalPurchaseCost;

use Greendot\EshopBundle\Entity\Project\AdditionalPurchaseCost;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class AdditionalPurchaseCostProvider
{
    private iterable $additionalPurchaseCosts;

    public function __construct(
        #[AutowireIterator('greendot.additional_purchase_cost')]
        iterable $additionalPurchaseCosts
    )
    {
        $this->additionalPurchaseCosts = $additionalPurchaseCosts;
    }

    public function get(Purchase $purchase): iterable
    {
        return $this->getApplicable($purchase);
    }

    public function getAdditionalPurchaseCosts(Purchase $purchase): iterable
    {
        return $this->getApplicable($purchase, true);
    }

    private function getApplicable(Purchase $purchase, bool $returnApplicable = false): iterable
    {
        foreach ($this->additionalPurchaseCosts as $additionalPurchaseCost) {
            assert($additionalPurchaseCost instanceof AdditionalPurchaseCostInterface);
            $cost = $additionalPurchaseCost->getApplicable($purchase);
            if (!$cost) continue;
            if ($returnApplicable){
                yield $cost;
            }else{
                yield $additionalPurchaseCost;
            }
        }
    }

}