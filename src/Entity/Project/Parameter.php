<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use Greendot\EshopBundle\ApiResource\ParameterCategoryFilter;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ParameterRepository::class)]
#[\ApiPlatform\Metadata\ApiResource(
    normalizationContext: ['groups' => ['parameter:read']],
    denormalizationContext: ['groups' => ['parameter:write']],
    paginationEnabled: false
)]
#[ApiFilter(ParameterCategoryFilter::class)]
class Parameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category:read', 'product_variant:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write', 'parameter:read', 'searchable', "SearchProductResultApiModel"])]
    private $id;

    #[ORM\Column(type: 'text')]
    #[Groups(['category:read', 'product_variant:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write', 'searchable', 'parameter:read', 'parameter:write'])]
    private $data;

    #[ORM\ManyToOne(targetEntity: ParameterGroup::class, inversedBy: 'parameter')]
    #[Groups(['product_variant:read', 'category:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write', 'searchable', 'parameter:read', 'parameter:write'])]
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
}
