<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\PurchaseProductVariantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Query\Expr\Func;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PurchaseProductVariantRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['order_product_variant:read']],
    denormalizationContext: ['groups' => ['order_product_variant:write']],
)]
class PurchaseProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:wishlist'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'ProductVariants')]
    private $purchase;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class, inversedBy: 'orderProductVariants', cascade: ['persist'])]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
    private $ProductVariant;

    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
    private $amount;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delivery_from = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delivery_until = null;

    #[Groups(['purchase:read', 'purchase:wishlist'])]
    /**
     * @deprecated use calculatedPrices instead  
    */ 
    private $total_price;

    #[ORM\OneToOne(targetEntity: Price::class, inversedBy: 'purchaseProductVariant', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private ?Price $price = null;

    #[Groups(['purchase:read', 'purchase:wishlist'])]
    private array $calculatedPrices = [];

    public function __construct()
    {

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): self
    {
        $this->purchase = $purchase;

        return $this;
    }

    /**
     * @return ProductVariant
     */
    public function getProductVariant(): ProductVariant
    {
        return $this->ProductVariant;
    }

    public function setProductVariant(?ProductVariant $productVariant): self
    {
        $this->ProductVariant = $productVariant;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getDeliveryFrom(): ?\DateTimeInterface
    {
        return $this->delivery_from;
    }

    public function setDeliveryFrom(?\DateTimeInterface $delivery_from): static
    {
        $this->delivery_from = $delivery_from;

        return $this;
    }

    public function getDeliveryUntil(): ?\DateTimeInterface
    {
        return $this->delivery_until;
    }

    public function setDeliveryUntil(?\DateTimeInterface $delivery_until): static
    {
        $this->delivery_until = $delivery_until;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTotalPrice(): float
    {
        return $this->total_price;
    }

    /**
     * @param float $total_price
     */
    public function setTotalPrice($total_price): void
    {
        $this->total_price = $total_price;
    }

    public function getPrice(): ?Price
    {
        return $this->price;
    }

    public function setPrice(?Price $price): self
    {
        $this->price = $price;

        return $this;
    }

    /* ApiPlatform field */
    #[Groups(['purchase:read', 'purchase:wishlist'])]
    public function isAvailable(): bool
    {
        $productVariant = $this->getProductVariant();

        $availability = $productVariant->getAvailability();
        if (!$availability) {
            // If no availability is set, consider the product variant as available
            return true;
        }

        return $availability->getIsPurchasable() ?? true;
    }

    public function setCalculatedPrices(array $calculatedPrices) : self
    {
        $this->calculatedPrices = $calculatedPrices;

        return $this;
    }

    public function getCalculatedPrices() : array
    {
        return $this->calculatedPrices;
    }
}
