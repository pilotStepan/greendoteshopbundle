<?php

namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Greendot\EshopBundle\Repository\Project\AdditionalPurchaseCostRepository;

#[ORM\Entity(repositoryClass: AdditionalPurchaseCostRepository::class)]
class AdditionalPurchaseCost implements Translatable
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Translatable]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Translatable]
    private ?string $description = null;

    #[ORM\OneToMany(targetEntity: HandlingPrice::class, mappedBy: 'additionalPurchaseCost')]
    private Collection $handlingPrices;

    #[ORM\ManyToMany(targetEntity: Purchase::class, mappedBy: 'additionalPurchaseCosts')]
    private Collection $purchases;

    #[Gedmo\Locale]
    private $locale;

    public function __construct()
    {
        $this->handlingPrices = new ArrayCollection();
    }

    public function setTranslatableLocale($locale): void
    {
        $this->locale = $locale;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, HandlingPrice>
     */
    public function getHandlingPrices(): Collection
    {
        return $this->handlingPrices;
    }

    public function addHandlingPrice(HandlingPrice $handlingPrice): static
    {
        if (!$this->handlingPrices->contains($handlingPrice)) {
            $this->handlingPrices->add($handlingPrice);
            $handlingPrice->setAdditionalPurchaseCost($this);
        }

        return $this;
    }

    public function removeHandlingPrice(HandlingPrice $handlingPrice): static
    {
        if ($this->handlingPrices->removeElement($handlingPrice)) {
            // set the owning side to null (unless already changed)
            if ($handlingPrice->getAdditionalPurchaseCost() === $this) {
                $handlingPrice->setAdditionalPurchaseCost(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases->add($purchase);
            $purchase->addAdditionalPurchaseCost($this);
        }

        return $this;
    }

    public function removePurchases(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase)) {
            $purchase->removeAdditionalPurchaseCost($this);
        }

        return $this;
    }

}
