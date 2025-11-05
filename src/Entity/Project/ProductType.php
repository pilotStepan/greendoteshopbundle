<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\ProductTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductTypeRepository::class)]
#[ApiResource()]
class ProductType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_item:read', 'purchase:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product_item:read', 'purchase:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product_item:read', 'purchase:read'])]
    private ?string $description = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(mappedBy: 'productType', targetEntity: Product::class)]
    private Collection $Products;

    public function __construct()
    {
        $this->Products = new ArrayCollection();
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
    public function getProducts(): Collection
    {
        return $this->Products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->Products->contains($product)) {
            $this->Products->add($product);
            $product->setProductType($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->Products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getProductType() === $this) {
                $product->setProductType(null);
            }
        }

        return $this;
    }
}
