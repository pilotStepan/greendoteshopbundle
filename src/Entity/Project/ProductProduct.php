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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentProduct(): ?Product
    {
        return $this->parentProduct;
    }

    public function setParentProduct(?Product $product1): static
    {
        $this->parentProduct = $product1;

        return $this;
    }

    public function getChildrenProduct(): ?Product
    {
        return $this->childrenProduct;
    }

    public function setChildrenProduct(?Product $product2): static
    {
        $this->childrenProduct = $product2;

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
}
