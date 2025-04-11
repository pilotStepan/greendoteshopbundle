<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductProductRepository::class)]
class ProductProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_info:read', 'product_info:write'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'childrenProducts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $parentProduct = null;

    #[ORM\ManyToOne(inversedBy: 'parentProducts')]
    #[Groups(['product_info:read', 'product_info:write'])]
    private ?Product $childrenProduct = null;

    #[ORM\Column]
    private ?int $type = null;

    #[ORM\Column]
    private ?int $sequence = null;

    #[ORM\Column]
    private ?int $discount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentProduct(): ?Product
    {
        return $this->parentProduct;
    }

    public function setParentProduct(?Product $parentProduct): static
    {
        $this->parentProduct = $parentProduct;

        return $this;
    }

    public function getChildrenProduct(): ?Product
    {
        return $this->childrenProduct;
    }

    public function setChildrenProduct(?Product $childrenProduct): static
    {
        $this->childrenProduct = $childrenProduct;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getDiscount(): ?int
    {
        return $this->discount;
    }

    public function setDiscount(int $discount): static
    {
        $this->discount = $discount;

        return $this;
    }
}
