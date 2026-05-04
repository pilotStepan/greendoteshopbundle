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

    /**
     * @param Purchase $purchase
     * @return AdditionalPurchaseCost[]
     */
    public function getEntities(Purchase $purchase): iterable
    {
        foreach ($this->additionalPurchaseCosts as $additionalPurchaseCost) {
            assert($additionalPurchaseCost instanceof AdditionalPurchaseCostInterface);
            if ($additionalPurchaseCost->isApplicable($purchase)){
                yield $additionalPurchaseCost->getAdditionalPurchaseCost();
            }
        }
    }

    /**
     * @param Purchase $purchase
     * @return AdditionalPurchaseCostInterface[]
     */
    public function get(Purchase $purchase): iterable
    {
        foreach ($this->additionalPurchaseCosts as $additionalPurchaseCost) {
            assert($additionalPurchaseCost instanceof AdditionalPurchaseCostInterface);
            if ($additionalPurchaseCost->isApplicable($purchase)){
                yield $additionalPurchaseCost;
            }
        }
    }

}