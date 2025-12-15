<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\AvailabilityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
class Availability
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist','product_variant:read', 'product_variant:write', 'product_item:read', 'product_list:read', 'product_info:write','searchable', "search_result", "SearchProductResultApiModel"])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist', 'product_variant:read', 'product_variant:write', 'product_item:read', 'product_list:read', 'product_info:write', 'searchable', "search_result", "SearchProductResultApiModel", 'purchase:wishlist'])]
    private $name;

    #[ORM\Column(type: 'text')]
    #[Groups(['product_list:read', 'product_item:read'])]
    private $description;

    #[ORM\Column(type: 'text')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist', 'product_variant:read', 'product_variant:write', 'product_item:read', 'product_list:read', 'product_info:write', 'searchable', "search_result", "SearchProductResultApiModel", 'purchase:wishlist'])]
    private $class;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist','product_list:read', 'product_item:read'])]
    private $isPurchasable;


    /**
     * @var int $sequence is used when determining product availability from variants. Lower sequence value takes priority.
     */
    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist','product_list:read', 'product_item:read'])]
    private $sequence = 1;

    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'availability')]
    private $productVariants;


    public function __construct()
    {
        $this->productVariants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getIsPurchasable(): ?bool
    {
        return $this->isPurchasable;
    }

    public function setIsPurchasable(bool $isPurchasable): self
    {
        $this->isPurchasable = $isPurchasable;

        return $this;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getProductVariants(): Collection
    {
        return $this->productVariants;
    }

    public function addProductVariant(ProductVariant $productVariant): self
    {
        if (!$this->productVariants->contains($productVariant)) {
            $this->productVariants[] = $productVariant;
            $productVariant->setAvailability($this);
        }

        return $this;
    }

    public function removeProductVariant(ProductVariant $productVariant): self
    {
        if ($this->productVariants->removeElement($productVariant)) {
            // set the owning side to null (unless already changed)
            if ($productVariant->getAvailability() === $this) {
                $productVariant->setAvailability(null);
            }
        }

        return $this;
    }
}
