<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Repository\Project\ClientDiscountRepository;

class DiscountService
{
    public function __construct(private ClientDiscountRepository $clientDiscountRepository) {}

    public function getValidClientDiscount(Client $client): ?ClientDiscount
    {
        $clientDiscount = $this->clientDiscountRepository->findCurrentClientDiscount($client);
        if ($clientDiscount and $this->validateDiscount($clientDiscount, $client)) {
            return $clientDiscount;
        } else {
            return null;
        }
    }

    /**
     * returns whether a discount is valid or not
     * $Client can be null only if discount type is not CLIENT_USE
     * @param ClientDiscount $clientDiscount
     * @param Client|null    $Client
     * @param \DateTime      $Datetime
     * @return bool
     */
    public function validateDiscount
    (
        ClientDiscount $clientDiscount,
        Client         $Client = null,
        \DateTime      $Datetime = new \DateTime(),
    ): bool
    {
        if ($Datetime < $clientDiscount->getDateStart()) {
            return false;
        }
        if ($clientDiscount->getDateEnd() != null && $Datetime > $clientDiscount->getDateEnd()) {
            return false;
        }

        switch ($clientDiscount->getType()) {
            case DiscountType::SingleClient:
                if ($Client == null || $clientDiscount->getClient()->getId() != $Client->getId()) {
                    return false;
                }
                break;
            case DiscountType::SingleUse:
                if ($clientDiscount->isIsUsed()) {
                    return false;
                }
                break;
            case DiscountType::MultiUse:
                break;
            case DiscountType::SingleUseClient:
                if ($Client == null || $clientDiscount->getClient()->getId() != $Client->getId() || $clientDiscount->isIsUsed()) {
                    return false;
                }
                break;
        }

        return true;
    }
}