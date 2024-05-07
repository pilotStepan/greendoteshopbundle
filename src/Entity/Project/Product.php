<?php

namespace Greendot\EshopBundle\Entity\Project;


use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\ApiResource\ProductFromAllSubCategories;
use App\ApiResource\ProductParameterSearch;
use App\ApiResource\ProductPriceSortFilter;
use App\ApiResource\ProductSearchFilter;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use App\Service\ProductInfoGetter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @Gedmo\Loggable()
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['product_info:read']],
    denormalizationContext: ['groups' => ['product_info:write']],
    paginationClientItemsPerPage: true
)]
#[ApiFilter(SearchFilter::class, properties: ['categoryProducts.category' => "exact"])]
#[ApiFilter(RangeFilter::class, properties: ['productVariants.stock'])]
#[ApiFilter(OrderFilter::class, properties: ['productVariants.price.price', 'sequence', 'id', 'externalId'])]
#[ApiFilter(ProductSearchFilter::class)]
#[ApiFilter(ProductPriceSortFilter::class)]
#[ApiFilter(ProductFromAllSubCategories::class)]
#[ApiFilter(ProductParameterSearch::class)]
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
    #[Groups(['product_info:read', 'product_info:write', 'searchable', 'search_result'])]
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
    #[Groups(['product_info:read', 'product_info:write', 'search_result'])]
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
    private $textGeneral;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, orphanRemoval: true)]
    #[Groups(['product_info:read', 'searchable'])]
    private $productVariants;

    #[ORM\ManyToOne(targetEntity: Producer::class, inversedBy: 'Product')]
    #[Groups(['product_info:read', 'product_info:write', 'search_result'])]
    private $producer;

    #[ORM\OneToMany(mappedBy: 'Product', targetEntity: Review::class)]
    private $reviews;

    #[ORM\Column(type: 'string', length: 255)]
    private $state = 'draft';

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: CategoryProduct::class, cascade: ['persist'])]
    #[Groups(['searchable'])]
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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductPerson::class)]
    private Collection $productPeople;

    public function __construct()
    {
        $this->productVariants = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->categoryProducts = new ArrayCollection();
        $this->productUploadGroup = new ArrayCollection();
        $this->productPeople = new ArrayCollection();
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
            // set the owning side to null (unless already changed)
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

}
