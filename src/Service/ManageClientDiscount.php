<?php

namespace Greendot\EshopBundle\Service;

use LogicException;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;

readonly class ManageClientDiscount
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @throws LogicException
     */
    public function guardUse(ClientDiscount $clientDiscount, Purchase $purchase): void
    {
        if ($clientDiscount->isValid() === false) {
            throw new LogicException('Slevový kupón není platný');
        }

        if ($purchase->getClientDiscount() !== null && $purchase->getClientDiscount() !== $clientDiscount) {
            throw new LogicException('Objednávka již má uplatněný slevový kupón');
        }

        $isClientSpecific = in_array($clientDiscount->getType(), [DiscountType::SingleClient, DiscountType::SingleUseClient], true);
        $belongsToDifferentClient = $isClientSpecific && $clientDiscount->getClient() !== $purchase->getClient();
        if ($belongsToDifferentClient) {
            throw new LogicException('Slevový kupón je určen pro jiného klienta.');
        }
    }

    /**
     * Apply a client discount to a purchase
     * @throws LogicException if the discount cannot be used for the purchase
     */
    public function use(ClientDiscount $clientDiscount, Purchase $purchase): void
    {
        $this->guardUse($clientDiscount, $purchase);

        $this->em->wrapInTransaction(function () use ($clientDiscount, $purchase): void {
            $purchase->setClientDiscount($clientDiscount);
            if (DiscountType::isSingleUse($clientDiscount->getType())) {
                $clientDiscount->setIsUsed(true);
            }
        });
    }
}