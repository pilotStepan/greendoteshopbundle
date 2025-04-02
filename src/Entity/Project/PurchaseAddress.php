<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

/**
 * Immutable snapshot of an address at purchase time, ensuring order history remains accurate even if client addresses change.
 */
#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['purchase_address:read']],
    denormalizationContext: ['groups' => ['purchase_address:write']]
)]
class PurchaseAddress extends Address
{
    #[ORM\OneToOne(targetEntity: Purchase::class, mappedBy: 'purchaseAddress')]
    private ?Purchase $purchase = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): static
    {
        $this->purchase = $purchase;
        return $this;
    }
}