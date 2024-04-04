<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Core\Annotation\ApiResource;
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
    #[Groups(['product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write','searchable', "search_result", "SearchProductResultApiModel"])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write', 'searchable', "search_result", "SearchProductResultApiModel"])]
    private $name;

    #[ORM\Column(type: 'text')]
    private $description;

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
