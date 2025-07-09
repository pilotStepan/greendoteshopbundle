<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\CategoryCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Table(name: 'p_category_category')]
#[ORM\Entity(repositoryClass: CategoryCategoryRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['category_category:read']],
    denormalizationContext: ['groups' => ['category_category:write']],
    paginationEnabled: true
)]
class CategoryCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category_category:read', 'category_category:write', 'category_with_parents:read'])]
    private $id;

    #[ORM\Column(type: 'integer')]
    #[Groups(['category_category:read', 'category_category:write'])]
    private $sequence;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'categoryCategories')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['category_with_parents:read', 'product_item:read'])]
    private $category_super;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'categorySubCategories')]
    #[ORM\JoinColumn(nullable: false)]
    private $category_sub;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['category_category:read', 'category_category:write'])]
    private $is_menu_item;

    public function __construct()
    {

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getCategorySuper(): ?Category
    {
        return $this->category_super;
    }

    public function setCategorySuper(?Category $category_super): self
    {
        $this->category_super = $category_super;

        return $this;
    }

    public function getCategorySub(): ?Category
    {
        return $this->category_sub;
    }

    public function setCategorySub(?Category $category_sub): self
    {
        $this->category_sub = $category_sub;

        return $this;
    }

    public function getIsMenuItem(): ?bool
    {
        return $this->is_menu_item;
    }

    public function setIsMenuItem(bool $is_menu_item): self
    {
        $this->is_menu_item = $is_menu_item;

        return $this;
    }
}
