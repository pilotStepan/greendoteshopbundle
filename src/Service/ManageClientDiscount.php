<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountType;

class ManageClientDiscount
{
    // Returns true if the discount is not used and within the valid date range
    public function isValid(ClientDiscount $clientDiscount) : bool
    {
        if ($clientDiscount->isIsUsed()) {
            return false;
        }

        $dateStart = $clientDiscount->getDateStart();
        $dateEnd = $clientDiscount->getDateEnd();
        $now = new \DateTime();

        // Return true if current time is within the discount period
        if ($dateStart !== null && $dateEnd !== null) {
            return $dateStart <= $now && $now <= $dateEnd;
        }

        return true;
    }

    // Returns true if the discount is valid and applicable to the purchase's client
    public function isAvailable(Purchase $purchase, ?ClientDiscount $clientDiscount) : bool
    {
        if (!$clientDiscount) return false;
        if (!$this->isValid($clientDiscount)) return false;

        // Check if discount is client-specific
        $isClientSpecific = in_array($clientDiscount->getType(), [DiscountType::SingleClient, DiscountType::SingleUseClient], true);

        // For client-specific discounts, ensure the client matches
        return !$isClientSpecific || $clientDiscount->getClient() === $purchase->getClient();
    }

    // Applies the discount (marks as used) if available; returns true if successful
    public  function use(Purchase $purchase, ?ClientDiscount $clientDiscount) : bool
    {
        if (!$clientDiscount) return false;
        if (!$this->isAvailable($purchase, $clientDiscount)) return false;

        // Mark discount as used for single-use discount types
        if (in_array($clientDiscount->getType(), [DiscountType::SingleUse, DiscountType::SingleUseClient], true)) {
            $clientDiscount->setIsUsed(true);
        }

        return true;
    }
}