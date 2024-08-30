<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\CategoryProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategoryProductRepository::class)]
class CategoryProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'categoryProducts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['searchable', 'product_info:read'])]
    private ?Product $product = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'categoryProducts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['searchable', 'product_info:read'])]
    private ?Category $category = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function __toString(): string
    {
        return $this->category->getName()." | ".$this->product->getExternalId(). " -> ".$this->product->getName();
    }
}
