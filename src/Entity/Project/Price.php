<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiProperty;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PriceRepository::class)]
class Price
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["SearchProductResultApiModel",'product_item:read', 'product_list:read'])]
    private ?float $price = null;

    #[ORM\Column]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?int $vat = null;

    #[ORM\Column]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?int $minimalAmount = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?float $discount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(["SearchProductResultApiModel",'product_item:read'])]
    private ?\DateTimeInterface $created = null;

    #[ORM\ManyToOne(inversedBy: 'price')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductVariant $productVariant = null;

    #[ORM\ManyToOne(inversedBy: 'price')]
    private ?Event $event = null;


    #[ORM\Column(nullable: true)]
    #[Groups(["SearchProductResultApiModel"])]
    private ?bool $isPackage = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["SearchProductResultApiModel"])]
    private ?float $minPrice = null;

    /**
     * @var array<string, float>
     *
     * Structure:
     * - priceNoVat:            basePrice + discount
     * - priceVat:              basePrice + discount + VAT
     * - priceNoVatNoDiscount:  basePrice
     * - priceVatNoDiscount:    basePrice + VAT
     */
    #[ApiProperty]
    private array $calculatedPrices = [];

    public function __construct()
    {

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getVat(): ?int
    {
        return $this->vat;
    }

    public function setVat(int $vat): self
    {
        $this->vat = $vat;

        return $this;
    }

    public function getMinimalAmount(): ?int
    {
        return $this->minimalAmount;
    }

    public function setMinimalAmount(int $minimalAmount): self
    {
        $this->minimalAmount = $minimalAmount;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeInterface $validFrom): self
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): self
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductVariant $productVariant): self
    {
        $this->productVariant = $productVariant;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }


    public function isIsPackage(): ?bool
    {
        return $this->isPackage;
    }

    public function setIsPackage(?bool $isPackage): self
    {
        $this->isPackage = $isPackage;

        return $this;
    }

    public function getMinPrice(): ?float
    {
        return $this->minPrice;
    }

    public function setMinPrice(?float $minPrice): self
    {
        $this->minPrice = $minPrice;

        return $this;
    }

    public function getCalculatedPrices(): array
    {
        return $this->calculatedPrices;
    }

    public function setCalculatedPrices(array $calculatedPrices): self
    {
        $this->calculatedPrices = $calculatedPrices;
        return $this;
    }
}
