<?php

namespace App\Entity\Project;

use App\Repository\Project\ProductProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductProductRepository::class)]
class ProductProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'product1Products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product1 = null;

    #[ORM\ManyToOne(inversedBy: 'product2Products')]
    private ?Product $product2 = null;

    #[ORM\Column]
    private ?int $type = null;

    #[ORM\Column]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct1(): ?Product
    {
        return $this->product1;
    }

    public function setProduct1(?Product $product1): static
    {
        $this->product1 = $product1;

        return $this;
    }

    public function getProduct2(): ?Product
    {
        return $this->product2;
    }

    public function setProduct2(?Product $product2): static
    {
        $this->product2 = $product2;

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
