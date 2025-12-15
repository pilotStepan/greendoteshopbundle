<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\ApiResource\ProductUploads;
use Greendot\EshopBundle\ApiResource\ProductVariantUploads;
use Greendot\EshopBundle\Enum\DownloadRestriction;
use Greendot\EshopBundle\Repository\Project\UploadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\ApiResource\ProductWithVariantsUploads;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UploadRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['upload:read']],
    denormalizationContext: ['groups' => ['upload:write']],
)]
#[ApiFilter(ProductUploads::class)]
#[ApiFilter(ProductVariantUploads::class)]
#[ApiFilter(ProductWithVariantsUploads::class)]
class Upload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['upload:read', 'category_default', 'category:read', 'category:write', 'product_item:read', 'product_list:read', 'producer_info:read', 'product_info:write', 'search_result', "SearchProductResultApiModel", 'purchase:read', 'comment:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['upload:read'])]
    private ?string $extension = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['upload:read'])]
    private ?string $mime = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['upload:read'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['upload:read'])]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['upload:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created = null;

    #[ORM\Column(length: 255)]
    #[Groups(['upload:read', 'category_default', 'category:read', 'category:write', 'product_item:read', 'product_list:read', 'producer_info:read', 'product_info:write', 'search_result', "SearchProductResultApiModel", 'purchase:read', 'comment:read'])]
    private ?string $path = null;

    #[ORM\Column(length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $width = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $height = null;

    #[ORM\ManyToOne(inversedBy: 'upload')]
    #[Groups(['upload:read'])]
    private ?UploadGroup $uploadGroup = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['upload:read'])]
    private ?int $sequence = null;

    #[ORM\OneToMany(mappedBy: 'upload', targetEntity: Product::class)]
    private Collection $products;

    #[ORM\OneToMany(mappedBy: 'upload', targetEntity: ProductVariant::class)]
    private Collection $productVariants;

    #[ORM\OneToMany(mappedBy: 'upload', targetEntity: Producer::class)]
    private Collection $producers;

    #[ORM\OneToMany(mappedBy: 'upload', targetEntity: Category::class)]
    private Collection $categories;

    /**
     * @var Collection<int, Person>
     */
    #[ORM\OneToMany(mappedBy: 'upload', targetEntity: Person::class)]
    private Collection $people;

    #[ORM\ManyToOne(inversedBy: 'upload')]
    #[Groups(['upload:read'])]
    private ?UploadType $uploadType = null;

    #[ORM\Column(type: 'integer', enumType: DownloadRestriction::class)]
    private DownloadRestriction $restriction = DownloadRestriction::NoRestrictions;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->productVariants = new ArrayCollection();
        $this->producers = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->people = new ArrayCollection();
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

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(?string $mime): self
    {
        $this->mime = $mime;

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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

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

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function setWidth(?string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?string
    {
        return $this->height;
    }

    public function setHeight(?string $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getUploadGroup(): ?UploadGroup
    {
        return $this->uploadGroup;
    }

    public function setUploadGroup(?UploadGroup $uploadGroup): self
    {
        $this->uploadGroup = $uploadGroup;

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

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setUpload($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getUpload() === $this) {
                $product->setUpload(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getProductVariants(): Collection
    {
        return $this->productVariants;
    }

    public function addProductVariant(ProductVariant $productVariant): self
    {
        if (!$this->productVariants->contains($productVariant)) {
            $this->productVariants->add($productVariant);
            $productVariant->setUpload($this);
        }

        return $this;
    }

    public function removeProductVariant(ProductVariant $productVariant): self
    {
        if ($this->productVariants->removeElement($productVariant)) {
            // set the owning side to null (unless already changed)
            if ($productVariant->getUpload() === $this) {
                $productVariant->setUpload(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Producer>
     */
    public function getProducers(): Collection
    {
        return $this->producers;
    }

    public function addProducer(Producer $producer): self
    {
        if (!$this->producers->contains($producer)) {
            $this->producers->add($producer);
            $producer->setUpload($this);
        }

        return $this;
    }

    public function removeProducer(Producer $producer): self
    {
        if ($this->producers->removeElement($producer)) {
            // set the owning side to null (unless already changed)
            if ($producer->getUpload() === $this) {
                $producer->setUpload(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setUpload($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getUpload() === $this) {
                $category->setUpload(null);
            }
        }

        return $this;
    }
    public function getRestriction(): DownloadRestriction
    {
        return $this->restriction;
    }

    public function getRestrictionValue(): int
    {
        return $this->restriction->value;
    }

    public function getRestrictionName(): string
    {
        return $this->restriction->name;
    }


    public function setRestriction(DownloadRestriction $restriction): self
    {
        $this->restriction = $restriction;

        return $this;
    }

    /**
     * @return Collection<int, Person>
     */
    public function getPeople(): Collection
    {
        return $this->people;
    }

    public function addPerson(Person $person): static
    {
        if (!$this->people->contains($person)) {
            $this->people->add($person);
            $person->setUpload($this);
        }

        return $this;
    }

    public function removePerson(Person $person): static
    {
        if ($this->people->removeElement($person)) {
            // set the owning side to null (unless already changed)
            if ($person->getUpload() === $this) {
                $person->setUpload(null);
            }
        }

        return $this;
    }

    public function getUploadType(): ?UploadType
    {
        return $this->uploadType;
    }

    public function setUploadType(?UploadType $uploadType): static
    {
        $this->uploadType = $uploadType;

        return $this;
    }
}
