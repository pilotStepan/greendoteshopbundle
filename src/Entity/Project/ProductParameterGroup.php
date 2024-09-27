<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\ProductParameterGroupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 *  Attaches required ParameterGroups to the product - these will be required for product variants definitions.
 */
#[ORM\Entity(repositoryClass: ProductParameterGroupRepository::class)]
class ProductParameterGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productParameterGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productParameterGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ParameterGroup $parameterGroup = null;

    /**
     * @var bool|null
     * Defines whether selected parameter group is used to define the product variant
     * For example size or color that has to be selected to add product to basket
     * This ParameterGroup should be required for each ProductVariant
     */
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

    public function getParameterGroup(): ?ParameterGroup
    {
        return $this->parameterGroup;
    }

    public function setParameterGroup(?ParameterGroup $parameterGroup): static
    {
        $this->parameterGroup = $parameterGroup;

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