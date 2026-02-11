<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\ProductProductTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductProductTypeRepository::class)]
#[ApiResource()]
class ProductProductType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_item:read', 'purchase:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product_item:read', 'purchase:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product_item:read', 'purchase:read'])]
    private ?string $description = null;

    /**
     * @var Collection<int, ProductProduct>
     */
    #[ORM\OneToMany(mappedBy: 'productProductType', targetEntity: ProductProduct::class)]
    private Collection $productProducts;

    public function __construct()
    {
        $this->productProducts = new ArrayCollection();
    }

    public function setId(?int $id) : self
    {
        $this->id = $id;

        return $this;
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
     * @return Collection<int, Product>
     */
    public function getProductProducts(): Collection
    {
        return $this->productProducts;
    }

    public function addProductProduct(ProductProduct $productProduct): static
    {
        if (!$this->productProducts->contains($productProduct)) {
            $this->productProducts->add($productProduct);
            $productProduct->setProductProductType($this);
        }

        return $this;
    }

    public function removeProductProduct(ProductProduct $productProduct): static
    {
        if ($this->productProducts->removeElement($productProduct)) {
            // set the owning side to null (unless already changed)
            if ($productProduct->getProductProductType() === $this) {
                $productProduct->setProductProductType(null);
            }
        }

        return $this;
    }
}
