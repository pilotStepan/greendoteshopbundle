<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Project\Parameter;
use App\Entity\Project\ParameterGroupType;
use App\Entity\Project\ParamGroupCategory;
use App\Repository\Project\ParameterGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: ParameterGroupRepository::class)]
class ParameterGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['category:read', 'category:write', 'product_variant:read', 'product_variant:write', 'product_info:read', 'product_info:write', 'searchable'])]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['searchable'])]
    private $unit;

    #[ORM\Column]
    private ?int $sequence = null;

    #[ORM\OneToMany(mappedBy: 'parameterGroup', targetEntity: Parameter::class)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    private $parameter;

    #[ORM\ManyToOne(inversedBy: 'parameterGroups')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['category:read', 'category:write'])]
    private ?ParameterGroupType $type = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['searchable'])]
    private ?bool $isProductParameter = null;

    #[ORM\ManyToOne(inversedBy: 'parameterGroup')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ParameterGroupFilterType $parameterGroupFilterType = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isFilter = null;

    #[ORM\OneToMany(mappedBy: 'parameterGroup', targetEntity: ParamGroupCategory::class)]
    private Collection $paramGroupCategories;

    public function __construct()
    {
        $this->parameter = new ArrayCollection();
        $this->paramGroupCategories = new ArrayCollection();
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

    /**
     * @return Collection<int, ParamGroupCategory>
     */
    public function getParamGroupCategories(): Collection
    {
        return $this->paramGroupCategories;
    }

    public function addParamGroupCategory(ParamGroupCategory $paramGroupCategory): static
    {
        if (!$this->paramGroupCategories->contains($paramGroupCategory)) {
            $this->paramGroupCategories->add($paramGroupCategory);
            $paramGroupCategory->setParameterGroup($this);
        }

        return $this;
    }

    public function removeParamGroupCategory(ParamGroupCategory $paramGroupCategory): static
    {
        if ($this->paramGroupCategories->removeElement($paramGroupCategory)) {
            // set the owning side to null (unless already changed)
            if ($paramGroupCategory->getParameterGroup() === $this) {
                $paramGroupCategory->setParameterGroup(null);
            }
        }

        return $this;
    }
}
