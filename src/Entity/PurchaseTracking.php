<?php

namespace App\Entity;

use Greendot\EshopBundle\Entity\Project\Purchase;
use App\Repository\PurchaseTrackingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseTrackingRepository::class)]
class PurchaseTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $trackingUrl = null;

    #[ORM\Column(nullable: true)]
    private ?float $packagePrice = null;

    #[ORM\ManyToOne(inversedBy: 'purchaseTrackings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $purchase = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(string $trackingUrl): static
    {
        $this->trackingUrl = $trackingUrl;

        return $this;
    }

    public function getPackagePrice(): ?float
    {
        return $this->packagePrice;
    }

    public function setPackagePrice(?float $packagePrice): static
    {
        $this->packagePrice = $packagePrice;

        return $this;
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
