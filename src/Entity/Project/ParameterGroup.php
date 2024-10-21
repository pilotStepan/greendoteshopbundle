<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\ApiResource\ParameterGroupValues;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 *  Defines the parameter details like unit (eq kg) and name (eq vaha)
 */
#[ORM\Entity(repositoryClass: ParameterRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['parameter:read']],
    denormalizationContext: ['groups' => ['parameter:write']],
    paginationEnabled: false
)]
#[ApiFilter(ParameterGroupValues::class)]
class ParameterGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['category:read', 'product_variant:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write', 'searchable'])]
    private $name;

    /**
     * @var
     * Defines the unit that should be displayed with the parameter value.     *
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['searchable', 'product_variant:read', 'product_info:read'])]
    private $unit;

    #[ORM\OneToMany(mappedBy: 'parameterGroup', targetEntity: Parameter::class)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    private $parameter;

    /**
     * @var ParameterGroupType|null
     * Defines whether the parameter group should be relevant to products / categories / blog etc
     */
    #[ORM\ManyToOne(inversedBy: 'parameterGroups')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['category:read', 'category:write'])]
    private ?ParameterGroupType $type = null;

    /**
     * @var bool|null
     * TODO Obsolete, remove
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['searchable'])]
    private ?bool $isProductParameter = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isFilter = null;

    #[ORM\OneToMany(mappedBy: 'parameterGroup', targetEntity: CategoryParameterGroup::class)]
    private Collection $parameterGroupCategories;

    /**
     * @var ParameterGroupFilterType|null
     * Defines the display template for filter
     */
    #[ORM\ManyToOne(inversedBy: 'parameterGroup')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ParameterGroupFilterType $parameterGroupFilterType = null;

    /**
     * @var Collection|ArrayCollection
     * Defines whether this parameter is required for selected product product variants.
     */
    #[ORM\OneToMany(mappedBy: 'parameterGroup', targetEntity: ProductParameterGroup::class)]
    private Collection $productParameterGroups;

    public function __construct()
    {
        $this->parameter = new ArrayCollection();
        $this->paramGroupCategories = new ArrayCollection();
        $this->productParameterGroups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * @return Collection<int, Parameter>
     */
    public function getParameter(): Collection
    {
        return $this->parameter;
    }

    public function addParameter(Parameter $parameter): self
    {
        if (!$this->parameter->contains($parameter)) {
            $this->parameter[] = $parameter;
            $parameter->setParameterGroup($this);
        }

        return $this;
    }

    public function removeParameter(Parameter $parameter): self
    {
        if ($this->parameter->removeElement($parameter)) {
            // set the owning side to null (unless already changed)
            if ($parameter->getParameterGroup() === $this) {
                $parameter->setParameterGroup(null);
            }
        }

        return $this;
    }

    public function __toString() {
        return $this->name;
    }

    public function getType(): ?ParameterGroupType
    {
        return $this->type;
    }

    public function setType(?ParameterGroupType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isIsProductParameter(): ?bool
    {
        return $this->isProductParameter;
    }

    public function setIsProductParameter(?bool $isProductParameter): self
    {
        $this->isProductParameter = $isProductParameter;

        return $this;
    }

    public function isIsFilter(): ?bool
    {
        return $this->isFilter;
    }

    public function setIsFilter(?bool $isFilter): self
    {
        $this->isFilter = $isFilter;

        return $this;
    }

    public function getParameterGroupFilterType(): ?ParameterGroupFilterType
    {
        return $this->parameterGroupFilterType;
    }

    public function setParameterGroupFilterType(?ParameterGroupFilterType $parameterGroupFilterType): static
    {
        $this->parameterGroupFilterType = $parameterGroupFilterType;

        return $this;
    }

    /**
     * @return Collection<int, CategoryParameterGroup>
     */
    public function getParameterGroupCategories(): Collection
    {
        return $this->parameterGroupCategories;
    }

    public function addParameterGroupCategory(CategoryParameterGroup $parameterGroupCategory): static
    {
        if (!$this->parameterGroupCategories->contains($parameterGroupCategory)) {
            $this->parameterGroupCategories->add($parameterGroupCategory);
            $parameterGroupCategory->setParameterGroup($this);
        }

        return $this;
    }

    public function removeParameterGroupCategory(CategoryParameterGroup $parameterGroupCategory): static
    {
        if ($this->parameterGroupCategories->removeElement($parameterGroupCategory)) {
            if ($parameterGroupCategory->getParameterGroup() === $this) {
                $parameterGroupCategory->setParameterGroup(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductParameterGroup>
     */
    public function getProductParameterGroups(): Collection
    {
        return $this->productParameterGroups;
    }

    public function addProductParameterGroup(ProductParameterGroup $productParameterGroup): static
    {
        if (!$this->productParameterGroups->contains($productParameterGroup)) {
            $this->productParameterGroups->add($productParameterGroup);
            $productParameterGroup->setParameterGroup($this);
        }

        return $this;
    }

    public function removeProductParameterGroup(ProductParameterGroup $productParameterGroup): static
    {
        if ($this->productParameterGroups->removeElement($productParameterGroup)) {
            if ($productParameterGroup->getParameterGroup() === $this) {
                $productParameterGroup->setParameterGroup(null);
            }
        }

        return $this;
    }
}
