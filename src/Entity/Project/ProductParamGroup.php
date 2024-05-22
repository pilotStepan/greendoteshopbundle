<?php

namespace Greendot\EshopBundle\Entity\Project;

use App\Entity\Project\ParameterGroup;
use App\Entity\Project\Product;
use App\Repository\Project\ProductParamGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductParamGroupRepository::class)]
class ProductParamGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productParamGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productParamGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ParameterGroup $paramGroup = null;

    #[ORM\Column]
    private ?bool $isVariant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getParamGroup(): ?ParameterGroup
    {
        return $this->paramGroup;
    }

    public function setParamGroup(?ParameterGroup $paramGroup): static
    {
        $this->paramGroup = $paramGroup;

        return $this;
    }

    public function isIsVariant(): ?bool
    {
        return $this->isVariant;
    }

    public function setIsVariant(bool $isVariant): static
    {
        $this->isVariant = $isVariant;

        return $this;
    }
}
