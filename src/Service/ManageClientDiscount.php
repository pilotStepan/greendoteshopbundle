<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;

class ManageClientDiscount
{


    // checks is_used and date, returns true if ok
    public function isValid(ClientDiscount $clientDiscount) : bool
    {
        if ($clientDiscount->isIsUsed()){
            return false;
        }

        $date = new \DateTime();
        if (!($clientDiscount->getDateStart() < $date && $date < $clientDiscount->getDateEnd()))
        {
            return false;
        }

        return true;
    }

    // check isValid() and client based on type, returns true if ok
    public function isAvailable(ClientDiscount $clientDiscount, Purchase $purchase) : bool
    {
        if (!$this->isValid($clientDiscount))
        {
            return false;
        }

        if (($clientDiscount->getType() == "SINGLE_CLIENT" || $clientDiscount->getType() == "SINGLE_USE_CLIENT") && $clientDiscount->getClient() !== $purchase->getClient())
        {
           return false;
        }

        return true;
    }

    // checks isAvailable and sets is_used=true based on type, returns false if failed, true if ok
    public  function use(ClientDiscount $clientDiscount, Purchase $purchase) : bool
    {
        if (!$this->isAvailable($clientDiscount, $purchase)){
            return false;
        }

        if ($clientDiscount->getType() == "SINGLE_USE" || $clientDiscount->getType() == "SINGLE_USE_CLIENT")
        {
            $clientDiscount->setIsUsed(true);
        }
        return true;
    }

}