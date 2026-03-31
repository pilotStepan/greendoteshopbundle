<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\Repository\Project\ProductParameterGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 *  Attaches required ParameterGroups to the product - these will be required for product variants definitions.
 */
#[ORM\Entity(repositoryClass: ProductParameterGroupRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
)]
class ProductParameterGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_item:read', 'product_list:read', 'product_product:read', 'comment:read','purchase:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productParameterGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productParameterGroups')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product_item:read', 'product_list:read', 'product_product:read', 'comment:read','purchase:read'])]
    private ?ParameterGroup $parameterGroup = null;

    /**
     * @var bool|null
     * Defines whether selected parameter group is used to define the product variant
     * For example size or color that has to be selected to add product to basket
     * This ParameterGroup should be required for each ProductVariant
     */
    #[ORM\Column]
    #[Groups(['product_item:read', 'product_list:read', 'product_product:read', 'comment:read','purchase:read'])]
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
