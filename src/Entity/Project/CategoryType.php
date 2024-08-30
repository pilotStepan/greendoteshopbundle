<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\CategoryTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategoryTypeRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['categoryType:read']],
    denormalizationContext: ['groups' => ['categoryType:write']],
    paginationEnabled: false
)]
class CategoryType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['categoryType:read', 'categoryType:write', 'category:read', 'category:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['categoryType:read', 'categoryType:write', 'category:read', 'category:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $template = null;

    #[ORM\OneToMany(mappedBy: 'categoryType', targetEntity: Category::class)]
    private Collection $Categories;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slugPrefix = null;

    #[ORM\Column(length: 255)]
    private ?string $controllerName = null;

    public function __construct()
    {
        $this->Categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;

        return $this;
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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): self
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->Categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->Categories->contains($category)) {
            $this->Categories->add($category);
            $category->setCategoryType($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->Categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getCategoryType() === $this) {
                $category->setCategoryType(null);
            }
        }

        return $this;
    }
    public function __toString(): string
    {
        return $this->name;
    }

    public function getSlugPrefix(): ?string
    {
        return $this->slugPrefix;
    }

    public function setSlugPrefix(?string $slugPrefix): self
    {
        $this->slugPrefix = $slugPrefix;

        return $this;
    }

    public function getControllerName(): ?string
    {
        return $this->controllerName;
    }

    public function setControllerName(string $controllerName): static
    {
        $this->controllerName = $controllerName;

        return $this;
    }
}
