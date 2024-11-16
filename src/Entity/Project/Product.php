<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\ApiResource\ProductAvailability;
use Greendot\EshopBundle\ApiResource\ProductFilterByDiscount;
use Greendot\EshopBundle\ApiResource\ProductFilterByReviews;
use Greendot\EshopBundle\ApiResource\ProductFromAllSubCategories;
use Greendot\EshopBundle\ApiResource\ProductLabel;
use Greendot\EshopBundle\ApiResource\ProductParameterSearch;
use Greendot\EshopBundle\ApiResource\ProductPriceSortFilter;
use Greendot\EshopBundle\ApiResource\ProductSearchFilter;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Greendot\EshopBundle\StateProvider\ProductStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @Gedmo\Loggable()
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: '/products/filter',
            provider: ProductStateProvider::class,
        ),
        new Get(),
        new Post(
            uriTemplate: '/products/filterPost',
            provider: ProductStateProvider::class,
        ),
        new Post(),
        new Put(),
        new Delete(),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['product_info:read']],
    denormalizationContext: ['groups' => ['product_info:write']],
    paginationClientItemsPerPage: true
)]
#[ApiFilter(SearchFilter::class, properties: ['categoryProducts.category' => "exact"])]
#[ApiFilter(RangeFilter::class, properties: ['productVariants.stock'])]
#[ApiFilter(OrderFilter::class, properties: ['productVariants.price.price', 'sequence', 'id', 'externalId', 'name'])]
#[ApiFilter(ProductSearchFilter::class)]
#[ApiFilter(ProductPriceSortFilter::class)]
#[ApiFilter(ProductFromAllSubCategories::class)]
#[ApiFilter(ProductParameterSearch::class)]
#[ApiFilter(ProductLabel::class)]
#[ApiFilter(ProductAvailability::class)]
#[ApiFilter(ProductFilterByReviews::class)]
#[ApiFilter(ProductFilterByDiscount::class)]
//#[ApiFilter(ProductPriceRangeFilter::class)]
class Product implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_info:read', 'product_info:write', "SearchProductResultApiModel"])]
    private $id;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product_info:read', 'product_info:write', 'searchable', 'search_result', 'purchase:read'])]
    private $name;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $menu_name;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'text')]
    #[Groups(['searchable', 'product_info:read'])]
    private $description;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $title;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product_info:read', 'product_info:write', 'search_result', 'purchase:read'])]
    private $slug;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['product_info:read', 'product_info:write'])]
    private $isActive;

    #[ORM\Column(type: 'integer')]
    private $sequence = 999;

    #[ORM\Column(type: 'text', nullable: true)]
    private $javascript;

    #[Gedmo\Translatable]
    #[Gedmo\Versioned]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['product_info:read', 'product_info:write'])]
    private $textGeneral;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['product_info:read'])]
    private $productVariants;

    #[ORM\ManyToOne(targetEntity: Producer::class, inversedBy: 'Product')]
    #[Groups(['product_info:read', 'product_info:write', 'search_result'])]
    private $producer;

    #[ORM\OneToMany(mappedBy: 'Product', targetEntity: Review::class)]
    private $reviews;

    #[ORM\Column(type: 'string', length: 255)]
    private $state = 'draft';

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: CategoryProduct::class, cascade: ['persist'])]
    #[Groups(['searchable', 'product_info:read'])]
    private Collection $categoryProducts;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['searchable', 'search_result'])]
    private ?string $externalId = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isIndexable = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[Groups(['search_result','product_info:read', 'product_info:write'])]
    private ?Upload $upload = null;

    #[ORM\OneToMany(mappedBy: 'Product', targetEntity: ProductUploadGroup::class)]
    #[Groups(['product_variant:read', 'product_variant:write', 'purchase:read'])]
    private Collection $productUploadGroup;

    #[Gedmo\Locale]
    private $locale;

    #[ApiProperty]
    #[Groups(['product_info:read'])]
    private string $priceFrom;

    #[ApiProperty]
    #[Groups(['product_info:read'])]
    private string $currencySymbol;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductPerson::class)]
    private Collection $productPeople;

    /**
     * @var Collection<int, Label>
     */
    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'products')]
    #[Groups(['product_info:read', 'product_info:write', 'search_result'])]
    private Collection $labels;

    #[Groups(['product_info:read', 'search_result'])]
    private ?string $availability = null;


    private array $parameters = [];

    #[Groups(['product_info:read', 'search_result'])]
    private ?string $imagePath = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductParameterGroup::class)]
    #[Groups(['product_info:read'])]
    private Collection $productParameterGroups;

    #[ORM\OneToMany(mappedBy: 'parentProduct', targetEntity: ProductProduct::class)]
    private Collection $childrenProducts;

    #[ORM\OneToMany(mappedBy: 'childProduct', targetEntity: ProductProduct::class)]
    private Collection $parentProducts;

    #[ORM\Column(nullable: true)]
    private ?bool $hasVariantPicture = null;

    #[ORM\Column(nullable: true)]
    private ?int $sold_amount = null;

    #[ORM\ManyToOne(inversedBy: 'Products')]
    private ?ProductType $productType = null;

    public function __construct()
    {
        $this->productVariants = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->categoryProducts = new ArrayCollection();
        $this->productUploadGroup = new ArrayCollection();
        $this->productPeople = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->productParameterGroups = new ArrayCollection();
        $this->childrenProducts = new ArrayCollection();
        $this->parentProducts = new ArrayCollection();
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

    public function setMenuName(?string $menu_name): self
    {
        $this->menu_name = $menu_name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

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

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
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

    public function getJavascript(): ?string
    {
        return $this->javascript;
    }

    public function setJavascript(?string $javascript): self
    {
        $this->javascript = $javascript;

        return $this;
    }

    public function getTextGeneral(): ?string
    {
        return $this->textGeneral;
    }

    public function setTextGeneral(?string $textGeneral): self
    {
        $this->textGeneral = $textGeneral;

        return $this;
    }

    /**
     * @return Collection|ProductVariant[]
     */
    public function getProductVariants(): Collection
    {
        return $this->productVariants;
    }

    public function addProductVariant(ProductVariant $productVariant): self
    {
        if (!$this->productVariants->contains($productVariant)) {
            $this->productVariants[] = $productVariant;
            $productVariant->setProduct($this);
        }

        return $this;
    }

    public function removeProductVariant(ProductVariant $productVariant): self
    {
        if ($this->productVariants->removeElement($productVariant)) {
            // set the owning side to null (unless already changed)
            if ($productVariant->getProduct() === $this) {
                $productVariant->setProduct(null);
            }
        }

        return $this;
    }

    public function getProducer(): ?Producer
    {
        return $this->producer;
    }

    public function setProducer(?Producer $producer): self
    {
        $this->producer = $producer;

        return $this;
    }

    /**
     * @return Collection|Review[]
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews[] = $review;
            $review->setProduct($this);
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getProduct() === $this) {
                $review->setProduct(null);
            }
        }

        return $this;
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
            $categoryProduct->setProduct($this);
        }

        return $this;
    }

    public function removeCategoryProduct(CategoryProduct $categoryProduct): self
    {
        if ($this->categoryProducts->removeElement($categoryProduct)) {
            // set the owning side to null (unless already changed)
            if ($categoryProduct->getProduct() === $this) {
                $categoryProduct->setProduct(null);
            }
        }

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

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
     * @return Collection<int, ProductVariantUploadGroup>
     */
    public function getProductUploadGroups(): Collection
    {
        return $this->productUploadGroup;
    }

    public function addProductUploadGroup(ProductUploadGroup $productUploadGroup): self
    {
        if (!$this->productUploadGroup->contains($productUploadGroup)) {
            $this->productUploadGroup->add($productUploadGroup);
            $productUploadGroup->setProductVariant($this);
        }

        return $this;
    }

    public function removeProductVariantUploadGroup(ProductUploadGroup $productUploadGroup): self
    {
        if ($this->productUploadGroup->removeElement($productUploadGroup)) {
            if ($productUploadGroup->getProductVariant() === $this) {
                $productUploadGroup->setProductVariant(null);
            }
        }

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }

    public function setPriceFrom(string $priceFrom):void
    {
        $this->priceFrom = $priceFrom;
    }

    public function getPriceFrom(): ?string
    {
        return $this->priceFrom;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    /**
     * @return Collection<int, ProductPerson>
     */
    public function getProductPeople(): Collection
    {
        return $this->productPeople;
    }

    public function addProductPerson(ProductPerson $productPerson): static
    {
        if (!$this->productPeople->contains($productPerson)) {
            $this->productPeople->add($productPerson);
            $productPerson->setProduct($this);
        }

        return $this;
    }

    public function removeProductPerson(ProductPerson $productPerson): static
    {
        if ($this->productPeople->removeElement($productPerson)) {
            // set the owning side to null (unless already changed)
            if ($productPerson->getProduct() === $this) {
                $productPerson->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): static
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    public function removeLabel(Label $label): static
    {
        $this->labels->removeElement($label);

        return $this;
    }

    public function getAvailability(): ?string
    {
        return $this->availability;
    }

    public function setAvailability(?string $availability): self
    {
        $this->availability = $availability;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): self
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getCurrencySymbol(): string
    {
        return $this->currencySymbol;
    }

    public function setCurrencySymbol(string $currencySymbol): void
    {
        $this->currencySymbol = $currencySymbol;
    }

    public function getChildrenProducts(): Collection
    {
        return $this->childrenProducts;
    }

    public function addChildrenProduct(ProductProduct $productProduct): self
    {
        if (!$this->childrenProducts->contains($productProduct)) {
            $this->childrenProducts->add($productProduct);
            $productProduct->setParentProduct($this);
        }

        return $this;
    }

    public function removeChildrenProduct(ProductProduct $productProduct): self
    {
        if ($this->childrenProducts->removeElement($productProduct)) {
            if ($productProduct->getParentProduct() === $this) {
                $productProduct->setParentProduct(null);
            }
        }

        return $this;
    }

    public function getParentProducts(): Collection
    {
        return $this->parentProducts;
    }

    public function addParentProduct(ProductProduct $productProduct): self
    {
        if (!$this->parentProducts->contains($productProduct)) {
            $this->parentProducts->add($productProduct);
            $productProduct->setChildProduct($this);
        }

        return $this;
    }

    public function removeParentProduct(ProductProduct $productProduct): self
    {
        if ($this->parentProducts->removeElement($productProduct)) {
            if ($productProduct->getChildProduct() === $this) {
                $productProduct->setChildProduct(null);
            }
        }

        return $this;
    }

    public function isHasVariantPicture(): ?bool
    {
        return $this->hasVariantPicture;
    }

    public function setHasVariantPicture(?bool $hasVariantPicture): static
    {
        $this->hasVariantPicture = $hasVariantPicture;

        return $this;
    }

    public function getSoldAmount(): ?int
    {
        return $this->sold_amount;
    }

    public function setSoldAmount(?int $sold_amount): static
    {
        $this->sold_amount = $sold_amount;

        return $this;
    }

    public function getProductType(): ?ProductType
    {
        return $this->productType;
    }

    public function setProductType(?ProductType $productType): static
    {
        $this->productType = $productType;

        return $this;
    }

    public function getProductParameterGroups(): Collection
    {
        return $this->productParameterGroups;
    }

    public function setProductParameterGroups(Collection $productParameterGroups): void
    {
        $this->productParameterGroups = $productParameterGroups;
    }
}
