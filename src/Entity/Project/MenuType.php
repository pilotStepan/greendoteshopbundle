<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\MenuTypeRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuTypeRepository::class)]
#[ApiResource]
class MenuType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $template = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $controllerName = null;

//    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'menuType')]
//    private Collection $categories;
    #[ORM\OneToMany(mappedBy: 'menu_type', targetEntity: CategoryMenuType::class)]
    private Collection $categories;

    public function __construct()
    {
        //$this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function getControllerName(): ?string
    {
        return $this->controllerName;
    }

    public function setControllerName(?string $controllerName): static
    {
        $this->controllerName = $controllerName;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }


    public function addCategories(CategoryMenuType $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setMenuType($this);
        }

        return $this;
    }

    public function removeCategory(CategoryMenuType $category): static
    {
        if ($this->categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getMenuType() === $this) {
                $category->setMenuType(null);
            }
        }

        return $this;
    }
}
