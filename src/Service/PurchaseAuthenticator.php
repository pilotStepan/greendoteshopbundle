<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;

/**
 * note: some functions seem unnecesarry (getClientKey, getSalt) but they exist in case inner logic changes
 */
class PurchaseAuthenticator
{
    private string $salt;

    public function __construct(
        string $salt,
    ) 
    {
        $this->salt = $salt;
    }

    /**
     * returns true if key matches purchase, false if key doesn't match purchase
     */
    public function validateClientKey(string $clientKey, Purchase $purchase) : bool
    {
        $validKey = $this->generateHash($purchase);
        return $clientKey === $validKey;
    }

    public function getClientKey(Purchase $purchase) : string 
    {
        return $this->generateHash($purchase);
    }

    private function generateHash(Purchase $purchase) : string
    {
        $inputString = $purchase->getId().$purchase->getDateIssue().$this->getSalt();
        return hash('sha256', $inputString);
    }

    private function getSalt() : string
    {
        return $this->salt;
    }
}