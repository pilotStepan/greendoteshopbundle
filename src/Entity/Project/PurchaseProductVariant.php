<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\PurchaseProductVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
    private $id;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'ProductVariants')]
    private $purchase;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class, inversedBy: 'orderProductVariants', cascade: ['persist'])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $ProductVariant;

    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $amount;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delivery_from = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $delivery_until = null;



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


}
