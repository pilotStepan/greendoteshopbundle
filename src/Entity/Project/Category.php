<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Translatable\Translatable;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints\Existence;

/**
 * @Gedmo\Loggable()
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'p_category')]
#[ApiResource(
    normalizationContext: ['groups' => ['category:read']],
    denormalizationContext: ['groups' => ['category:write']],
    paginationEnabled: true,
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: '/categories-with-parents',
            normalizationContext: ['groups' => ['category_with_parents:read']],
            forceEager: false
        ),
        new Get(),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact','categorySubCategories.category_super' => 'exact', 'isActive'  => 'exact', 'name' => 'partial', 'categoryProducts.product' => 'exact', 'categoryType.id' => 'exact'])]
//#[ApiFilter(TranslationAwareSearchFilter::class)]
#[ApiFilter(ExistsFilter::class, properties: ['comments'])]
class Category implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category_with_parents:read', 'category_default', 'category:read', 'category:write', 'product_item:read', 'comment:read', 'searchable', 'category_category:read', 'category_category:write'])]
    private $id;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 150)]
    #[Groups(['category_with_parents:read','category_default', 'category:read', 'category:write', 'searchable', 'category_category:read', 'category_category:write', 'product_item:read', 'comment:read'])]
    private $name;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    #[Groups(['category_default', 'category:read', 'category:write', 'searchable', 'product_item:read', 'comment:read'])]
    private $menu_name;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['category_default', 'category:read', 'category:write', 'product_item:read', 'comment:read'])]
    private $description;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['category_default', 'category:read', 'category:write'])]
    private $html;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    #[Groups(['category_default', 'category:read', 'category:write', 'product_item:read', 'comment:read'])]
    private $title;

    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'categories')]
    #[Groups(['category_default', 'category:read', 'category:write'])]
    private $labels;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryFile::class)]
    #[Groups('category_default')]
    private $categoryFiles;

    #[ORM\OneToMany(mappedBy: 'category_super', targetEntity: CategoryCategory::class)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    #[Groups(['category_default', 'category:read', 'category:write'])]
    private $categoryCategories;

    #[ORM\OneToMany(mappedBy: 'category_sub', targetEntity: CategoryCategory::class)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    #[Groups(['category_with_parents:read'])]
    private $categorySubCategories;

    #[Gedmo\Versioned]
    #[ORM\Column(type: 'boolean', name: 'is_active')]
    #[Groups('category_with_parents:read','category_default', 'category:read', 'product_item:read', 'comment:read')]
    private $isActive;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    #[Groups(['category_with_parents:read','category_default', 'category:read', 'category:write', 'product_item:read', 'comment:read'])]
    private $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private $javascript;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['category_with_parents:read','category_default', 'category:read', 'category:write'])]
    private $sequence;

    #[ORM\Column(type: 'smallint', nullable: true, options: ['default' => 1])]
    private $is_menu = true;

    #[Gedmo\Versioned]
    #[ORM\Column(type: 'float', nullable: true)]
    private $latitude;

    #[Gedmo\Versioned]
    #[ORM\Column(type: 'float', nullable: true)]
    private $longitude;

    #[Gedmo\Locale]
    private $locale;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $state;

    #[ORM\OneToMany(targetEntity: Parameter::class, mappedBy: 'category')]
    #[Groups(['category:read', 'category:write'])]
    private $parameters;

//    /**
//     * @ORM\OneToMany(targetEntity=CategoryProduct::class, mappedBy="category", orphanRemoval=true)
//     */
    #[Ignore]
    #[ORM\OneToMany(targetEntity: CategoryProduct::class, mappedBy: 'category')]
    private $categoryProducts;

    #[ORM\ManyToMany(targetEntity: Comment::class, mappedBy: 'categories')]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryPerson::class)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    private Collection $persons;

    #[ORM\Column(nullable: true)]
    private ?bool $hasComments = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryEvent::class)]
    #[ORM\OrderBy(['sequence' => 'ASC'])]
    private Collection $events;

    #[ORM\OneToMany(mappedBy: 'Category', targetEntity: CategoryUploadGroup::class, cascade: ['persist'])]
    #[Groups([/*'category_with_parents:read',*/'category_default', 'category:read', 'category:write'])]
    private Collection $categoryUploadGroups;

    #[ORM\ManyToOne(inversedBy: 'Categories')]
    #[Groups(['category_default', 'category:read'])]
    private ?CategoryType $categoryType = null;

    #[ORM\Column(nullable: true, options: ['default' => 1])]
    private ?bool $isIndexable = null;

    #[ORM\ManyToOne(inversedBy: 'categories')]
    #[Groups(['category_with_parents:read'])]
    private ?Upload $upload = null;

    #[ORM\ManyToMany(targetEntity: MenuType::class, inversedBy: 'categories')]
    private Collection|null $menuType = null;

    #[ORM\ManyToMany(targetEntity: SubMenuType::class ,inversedBy: 'categories')]
    #[MaxDepth(1)]
    private Collection|null $subMenuType = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryParameterGroup::class)]
    #[MaxDepth(1)]
    #[Groups(['category:read'])]
    private Collection $parameterGroupCategories;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
        $this->categoryFiles = new ArrayCollection();
        $this->categoryCategories = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->categoryProducts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->persons = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->categoryUploadGroups = new ArrayCollection();
        $this->menuType = new ArrayCollection();
        $this->subMenuType = new ArrayCollection();
        $this->parameterGroupCategories = new ArrayCollection();
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

    public function getMenuName(): ?string
    {
        return $this->menu_name;
    }

    public function setMenuName(string $menu_name): self
    {
        $this->menu_name = $menu_name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function setHtml(?string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection|Label[]
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): self
    {
        if (!$this->labels->contains($label)) {
            $this->labels[] = $label;
        }

        return $this;
    }

    public function removeLabel(Label $label): self
    {
        $this->labels->removeElement($label);

        return $this;
    }

    /**
     * @return Collection|CategoryFile[]
     */
    public function getCategoryFiles(): Collection
    {
        return $this->categoryFiles;
    }

    public function addCategoryFile(CategoryFile $categoryFile): self
    {
        if (!$this->categoryFiles->contains($categoryFile)) {
            $this->categoryFiles[] = $categoryFile;
            $categoryFile->setCategory($this);
        }

        return $this;
    }

    public function removeCategoryFile(CategoryFile $categoryFile): self
    {
        if ($this->categoryFiles->removeElement($categoryFile)) {
            // set the owning side to null (unless already changed)
            if ($categoryFile->getCategory() === $this) {
                $categoryFile->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CategoryCategory[]
     */
    public function getCategoryCategories(): Collection
    {
        return $this->categoryCategories;
    }

    /**
     * @return Collection|CategoryCategory[]
     */
    public function getCategorySubCategories(): Collection
    {
        return $this->categorySubCategories;
    }

    public function addCategoryCategory(CategoryCategory $categoryCategory): self
    {
        if (!$this->categoryCategories->contains($categoryCategory)) {
            $this->categoryCategories[] = $categoryCategory;
            $categoryCategory->setCategorySuper($this);
        }

        return $this;
    }

    public function removeCategoryCategory(CategoryCategory $categoryCategory): self
    {
        if ($this->categoryCategories->removeElement($categoryCategory)) {
            // set the owning side to null (unless already changed)
            if ($categoryCategory->getCategorySuper() === $this) {
                $categoryCategory->setCategorySuper(null);
            }
        }

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getJavascript(): ?string
    {
        return $this->javascript;
    }

    public function setJavascript(?string $javascript): self
    {
        $this->javascript = $javascript;

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

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return Collection<int, Parameter>
     */
    public function getParameters(): Collection
    {
        return $this->parameters;
    }

    public function addParameter(Parameter $parameter): self
    {
        if (!$this->parameters->contains($parameter)) {
            $this->parameters[] = $parameter;
            $parameter->setCategory($this);
        }

        return $this;
    }

    public function removeParameter(Parameter $parameter): self
    {
        if ($this->parameters->removeElement($parameter)) {
            // set the owning side to null (unless already changed)
            if ($parameter->getCategory() === $this) {
                $parameter->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CategoryProduct>
     */
    public function getCategoryProducts(): Collection
    {
        return $this->categoryProducts;
    }

    public function addCategoryProduct(CategoryProduct $categoryProduct): self
    {
        if (!$this->categoryProducts->contains($categoryProduct)) {
            $this->categoryProducts->add($categoryProduct);
            $categoryProduct->setCategory($this);
        }

        return $this;
    }

    public function removeCategoryProduct(CategoryProduct $categoryProduct): self
    {
        if ($this->categoryProducts->removeElement($categoryProduct)) {
            // set the owning side to null (unless already changed)
            if ($categoryProduct->getCategory() === $this) {
                $categoryProduct->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        $this->comments->removeElement($comment);

        return $this;
    }

    /**
     * @return Collection<int, CategoryPerson>
     */
    public function getPersons(): Collection
    {
        return $this->persons;
    }

    public function addPerson(CategoryPerson $person): self
    {
        if (!$this->persons->contains($person)) {
            $this->persons->add($person);
            $person->setCategory($this);
        }

        return $this;
    }

    public function removePerson(CategoryPerson $person): self
    {
        if ($this->persons->removeElement($person)) {
            // set the owning side to null (unless already changed)
            if ($person->getCategory() === $this) {
                $person->setCategory(null);
            }
        }

        return $this;
    }

    public function isHasComments(): ?bool
    {
        return $this->hasComments;
    }

    public function setHasComments(bool $hasComments): self
    {
        $this->hasComments = $hasComments;

        return $this;
    }

    /**
     * @return Collection<int, CategoryEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(CategoryEvent $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setCategory($this);
        }

        return $this;
    }

    public function removeEvent(CategoryEvent $event): self
    {
        if ($this->events->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getCategory() === $this) {
                $event->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CategoryUploadGroup>
     */
    public function getCategoryUploadGroups(): Collection
    {
        return $this->categoryUploadGroups;
    }

    public function addCategoryUploadGroup(CategoryUploadGroup $categoryUploadGroup): self
    {
        if (!$this->categoryUploadGroups->contains($categoryUploadGroup)) {
            $this->categoryUploadGroups->add($categoryUploadGroup);
            $categoryUploadGroup->setCategory($this);
        }

        return $this;
    }

    public function removeCategoryUploadGroup(CategoryUploadGroup $categoryUploadGroup): self
    {
        if ($this->categoryUploadGroups->removeElement($categoryUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($categoryUploadGroup->getCategory() === $this) {
                $categoryUploadGroup->setCategory(null);
            }
        }

        return $this;
    }

    public function getCategoryType(): ?CategoryType
    {
        return $this->categoryType;
    }

    public function setCategoryType(?CategoryType $categoryType): self
    {
        $this->categoryType = $categoryType;

        return $this;
    }

    public function isIsIndexable(): ?bool
    {
        return $this->isIndexable;
    }

    public function setIsIndexable(?bool $isIndexable): self
    {
        $this->isIndexable = $isIndexable;

        return $this;
    }

    public function getUpload(): ?Upload
    {
        return $this->upload;
    }

    public function setUpload(?Upload $upload): self
    {
        $this->upload = $upload;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getMenuType(): Collection
    {
        return $this->menuType;
    }

    public function addMenuType(MenuType $menuType): self
    {
        if (!$this->menuType->contains($menuType)) {
            $this->menuType[] = $menuType;
        }

        return $this;
    }

    public function removeMenuType(Label $menuType): self
    {
        $this->menuType->removeElement($menuType);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getSubMenuType(): Collection
    {
        return $this->subMenuType;
    }

    public function addSubMenuType(SubMenuType $subMenuType): self
    {
        if (!$this->subMenuType->contains($subMenuType)) {
            $this->subMenuType[] = $subMenuType;
        }

        return $this;
    }

    public function removeSubMenuType(Label $subMenuType): self
    {
        $this->subMenuType->removeElement($subMenuType);

        return $this;
    }

//    /**
//     * @throws Exception
//     */
//    public function getIF(): array
//    {
//        return $this->getInformationBlocks($this);
//    }

    /**
     * @return Collection<int, CategoryParameterGroup>
     */
    #[Groups(['category:read'])]
    public function getParameterGroupCategories(): Collection
    {
        return $this->parameterGroupCategories;
    }

    public function addParameterGroupCategory(CategoryParameterGroup $parameterGroupCategory): static
    {
        if (!$this->parameterGroupCategories->contains($parameterGroupCategory)) {
            $this->parameterGroupCategories->add($parameterGroupCategory);
            $parameterGroupCategory->setCategory($this);
        }

        return $this;
    }

    public function removeParameterGroupCategory(CategoryParameterGroup $parameterGroupCategory): static
    {
        if ($this->parameterGroupCategories->removeElement($parameterGroupCategory)) {
            if ($parameterGroupCategory->getCategory() === $this) {
                $parameterGroupCategory->setCategory(null);
            }
        }

        return $this;
    }
}
