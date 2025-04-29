<?php

namespace Greendot\EshopBundle\Service;

use App\Entity\Project\Client;
use App\Entity\Project\ClientDiscount;
use App\Enum\DiscountType;
use App\Repository\Project\ClientDiscountRepository;

class DiscountService
{
    public function __construct(private readonly ClientDiscountRepository $clientDiscountRepository){}

    public function getValidClientDiscount(Client $client):?ClientDiscount
    {
        $clientDiscount = $this->clientDiscountRepository->findCurrentClientDiscount($client);
        if ($clientDiscount and $this->validateDiscount($clientDiscount, $client)){
            return $clientDiscount;
        }else{
            return null;
        }
    }

    /**
     * returns whether a discount is valid or not
     * $Client can be null only if discount type is not CLIENT_USE
     * @param ClientDiscount $ClientDiscount
     * @param Client|null $Client
     * @param \DateTime $Datetime
     * @return bool
     */
    public function validateDiscount
    (
        ClientDiscount $ClientDiscount,
        Client         $Client = null,
        \DateTime       $Datetime = new \DateTime()
    ): bool
    {
        if ($Datetime < $ClientDiscount->getDateStart()) {
            return false;
        }
        if ($ClientDiscount->getDateEnd() != null && $Datetime > $ClientDiscount->getDateEnd()) {
            return false;
        }

        switch ($ClientDiscount->getType()) {
            case DiscountType::SingleClient:
                if ($Client == null || $ClientDiscount->getClient()->getId() != $Client->getId()) {
                    return false;
                }
                break;
            case DiscountType::SingleUse:
                if ($ClientDiscount->isIsUsed()) {
                    return false;
                }
                break;
            case DiscountType::MultiUse:
                break;
        }

        return true;
    }
}