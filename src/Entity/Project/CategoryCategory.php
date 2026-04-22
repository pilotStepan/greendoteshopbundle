<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\Repository\Project\CategoryCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\Entity\Trait\MainCategoryTrait;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: 'p_category_category')]
#[ORM\Entity(repositoryClass: CategoryCategoryRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['category_category:read']],
    denormalizationContext: ['groups' => ['category_category:write']],
    paginationEnabled: true
)]
#[ORM\UniqueConstraint(
    name: 'unique_main_category_per_sub_category',
    columns: ['category_sub_id'],
    options: ['where' => 'is_main_category = true']
)]
class CategoryCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category_category:read', 'category_category:write', 'category_with_parents:read'])]
    private $id;

    use MainCategoryTrait;

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
