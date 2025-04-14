<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountType;

class ManageClientDiscount
{


    // checks is_used and date, returns true if ok
    public function isValid(ClientDiscount $clientDiscount) : bool
    {
        if ($clientDiscount->isIsUsed())
        {
            return false;
        }

        $dateStart = $clientDiscount->getDateStart();
        $dateEnd = $clientDiscount->getDateEnd();
        $now = new \DateTime();

        if ($dateStart !== null && $dateEnd !== null){
            return $dateStart <= $now && $now <= $clientDiscount->$dateEnd;
        }
        return true;

    }

    // check isValid() and client based on type, returns true if ok
    public function isAvailable(Purchase $purchase, ?ClientDiscount $clientDiscount) : bool
    {
        if (!$clientDiscount) return false;
        if (!$this->isValid($clientDiscount)) return false;

        $isClientSpecific = in_array($clientDiscount->getType(), [DiscountType::SingleClient, DiscountType::SingleUseClient], true);

        return !$isClientSpecific || $clientDiscount->getClient() === $purchase->getClient();
    }

    // checks isAvailable and sets is_used=true based on type, returns false if failed, true if ok
    public  function use(Purchase $purchase, ?ClientDiscount $clientDiscount) : bool
    {
        if (!$clientDiscount) return false;
        if (!$this->isAvailable($purchase, $clientDiscount)) return false;

        if (in_array($clientDiscount->getType(), [DiscountType::SingleUse, DiscountType::SingleUseClient], true)) {
            $clientDiscount->setIsUsed(true);
        }

        return true;
    }
}