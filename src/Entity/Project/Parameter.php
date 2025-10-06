<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use Greendot\EshopBundle\ApiResource\ParameterCategoryFilter;
use Greendot\EshopBundle\ApiResource\ParameterSupplierFilter;
use Greendot\EshopBundle\ApiResource\ParameterDiscountFilter;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\StateProvider\FilteredParametersStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ParameterRepository::class)]
#[ApiResource(
      operations: [
        new Get(normalizationContext: ['groups' => ['parameter:read']]),
        new GetCollection(normalizationContext: ['groups' => ['parameter:read']]),
        new Post(denormalizationContext: ['groups' => ['parameter:write']]),
        new Patch(denormalizationContext: ['groups' => ['parameter:write']]),
        new Delete(),

        new GetCollection(
            uriTemplate: '/parametersFiltered',
            normalizationContext: ['groups' => ['parameter_filtered:read']],
            provider: FilteredParametersStateProvider::class,
        )
    ],
    paginationEnabled: false
)]
// TODO: make endpoint for productListingParametrFetch
// FIXME: doesn't fetch params from subcategories
// TODO: set the filters correctly (should be filtered only when the parameter is sent)
// #[ApiFilter(ParameterCategoryFilter::class)]
// #[ApiFilter(ParameterSupplierFilter::class)]
// #[ApiFilter(ParameterDiscountFilter::class)]
class Parameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['parameter_filtered:read', 'category:read', 'product_variant:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_item:read', 'product_list:read', 'product_info:write', 'comment:read','parameter:read', 'searchable', "SearchProductResultApiModel"])]
    private $id;

    #[ORM\Column(type: 'text')]
    #[Groups(['parameter_filtered:read', 'category:read', 'product_variant:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_item:read', 'product_list:read', 'product_info:write', 'comment:read','searchable', 'parameter:read', 'parameter:write', 'purchase:read', 'purchase:wishlist'])]
    private $data;

    #[ORM\ManyToOne(targetEntity: ParameterGroup::class, inversedBy: 'parameter')]
    #[Groups(['parameter_filtered:read', 'product_variant:read', 'category:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_item:read', 'product_list:read', 'product_info:write', 'comment:read','searchable', 'parameter:read', 'parameter:write', 'purchase:read', 'purchase:wishlist'])]
    private $parameterGroup;

    #[Groups(['parameter:read', 'parameter:write'])]
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'parameters')]
    private $category;

    #[Groups(['parameter:read', 'parameter:write'])]
    #[ORM\ManyToOne(targetEntity: ProductVariant::class, inversedBy: 'parameters')]
    private $productVariant;

    #[ORM\ManyToOne(inversedBy: 'parameters')]
    private ?Person $person = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    #[ApiProperty]
    #[Groups(['parameter_filtered:read', 'product_item:read', 'product_list:read', 'parameter:read', 'purchase:read', 'purchase:wishlist'])]
    private ?string $colorName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getParameterGroup(): ?ParameterGroup
    {
        return $this->parameterGroup;
    }

    public function setParameterGroup(?ParameterGroup $parameterGroup): self
    {
        $this->parameterGroup = $parameterGroup;

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

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductVariant $productVariant): self
    {
        $this->productVariant = $productVariant;

        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(?Person $person): self
    {
        $this->person = $person;

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

    public function getColorName(): ?string
    {
        return $this->colorName;
    }

    public function setColorName(?string $ColorName): self
    {
        $this->colorName = $ColorName;

        return $this;
    }

}
