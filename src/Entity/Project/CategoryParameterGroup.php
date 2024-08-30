<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\CategoryParameterGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CategoryParameterGroupRepository::class)]
#[ApiResource]
class CategoryParameterGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:read', 'category_parameter_group:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'parameterGroupCategories')]
    #[ORM\JoinColumn(nullable: false)]
    #[MaxDepth(1)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'parameterGroupCategories')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['category:read', 'category_parameter_group:read'])]
    #[MaxDepth(1)]
    private ?ParameterGroup $parameterGroup = null;

    #[ORM\Column]
    #[Groups(['category:read', 'category_parameter_group:read', 'category_parameter_group:write'])]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

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

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }

    #[Groups(['category_parameter_group:read'])]
    public function getCategoryId(): ?int
    {
        return $this->category ? $this->category->getId() : null;
    }

    #[Groups(['category_parameter_group:read'])]
    public function getCategoryName(): ?string
    {
        return $this->category ? $this->category->getName() : null;
    }
}
