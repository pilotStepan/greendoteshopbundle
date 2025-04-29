<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Repository\Project\ClientDiscountRepository;

readonly class DiscountService
{
    public function __construct(private ClientDiscountRepository $clientDiscountRepository){}

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
            case DiscountType::SingleUseClient:
                // TODO: validation for SingleUseClient discount (?)
                throw new \Exception('To be implemented');
        }

        return true;
    }
}