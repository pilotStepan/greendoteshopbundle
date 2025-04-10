<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\ApiResource\ProductVariantFilter;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['product_variant:read']],
    denormalizationContext: ['groups' => ['product_variant:write']],
)]
#[ApiFilter(ProductVariantFilter::class)]
class ProductVariant implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_variant:read', 'product_variant:write', 'product_info:read', 'comment:read', 'product_info:write', 'searchable', "search_result", "SearchProductResultApiModel", 'purchase:read'])]
    private $id;

    #[Gedmo\Versioned]
    #[Gedmo\Translatable]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product_info:read', 'comment:read', 'product_variant:read', 'product_variant:write', 'purchase:read', 'purchase:write', "SearchProductResultApiModel"])]
    private $name;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['product_variant:read', 'product_variant:write' , 'product_info:read', 'comment:read', 'product_info:write', "SearchProductResultApiModel"])]
    private $stock;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['product_variant:read', 'product_variant:write', 'searchable', "SearchProductResultApiModel"])]
    private $externalId;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productVariants')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product_variant:read', 'purchase:read'])]
    private $product;

    #[ORM\OneToMany(mappedBy: 'ProductVariant', targetEntity: PurchaseProductVariant::class)]
    private $orderProductVariants;

    /*
     * TO-DO remove
     */
    #[ORM\ManyToOne(targetEntity: Colour::class, inversedBy: 'productVariants')]
    #[Groups(['product_variant:read', 'product_variant:write', "SearchProductResultApiModel"])]
    private $colour;

    #[ORM\OneToMany(targetEntity: Video::class, mappedBy: 'productVariant')]
    #[Groups(['product_variant:read', 'product_variant:write', "SearchProductResultApiModel"])]
    private $video;

    #[ORM\ManyToOne(targetEntity: Availability::class, inversedBy: 'productVariants')]
    #[Groups(['product_variant:read', 'product_variant:write', 'product_info:read', 'comment:read', 'product_info:write', 'searchable', "search_result", "SearchProductResultApiModel"])]
    private $availability;

    #[ORM\OneToMany(targetEntity: Parameter::class, mappedBy: 'productVariant', cascade: ['persist'])]
    #[Groups(['searchable', "SearchProductResultApiModel", 'product_variant:read', 'product_info:read', 'comment:read', 'purchase:read'])]
    private $parameters;

    #[ORM\OneToMany(mappedBy: 'productVariant', targetEntity: Price::class)]
    #[Groups(['product_variant:read', 'product_info:read', 'comment:read', "SearchProductResultApiModel"])]
    private Collection $price;

    #[ORM\Column(nullable: true)]
    #[Groups(["SearchProductResultApiModel"])]
    private ?int $AvgRestockDays = null;

    #[ORM\ManyToOne(inversedBy: 'productVariants')]
    //#[Groups(["SearchProductResultApiModel"])]
    #[Groups(['product_variant:read', 'purchase:read'])]
    private ?Upload $upload = null;

    #[ORM\OneToMany(mappedBy: 'ProductVariant', targetEntity: ProductVariantUploadGroup::class)]
    #[Groups(['product_variant:read', 'product_variant:write'])]
    private Collection $productVariantUploadGroups;

    #[ORM\Column(nullable: true)]
    #[Groups(["SearchProductResultApiModel"])]
    private ?bool $isActive = null;

    #[Gedmo\Locale]
    private $locale;

    public function __construct()
    {
        $this->video = new ArrayCollection();
        $this->orderProductVariants = new ArrayCollection();
        $this->price = new ArrayCollection();
        $this->productVariantUploadGroups = new ArrayCollection();
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

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): self
    {
        $this->stock = $stock;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId= $externalId;

        return $this;
    }


    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getColour(): ?Colour
    {
        return $this->colour;
    }

    public function setColour(?Colour $colour): self
    {
        $this->colour = $colour;

        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getVideo(): Collection
    {
        return $this->video;
    }

    public function addVideo(Video $video): self
    {
        if (!$this->video->contains($video)) {
            $this->video[] = $video;
            $video->setProductVariant($this);
        }

        return $this;
    }

    public function removeVideo(Video $video): self
    {
        if ($this->video->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getProductVariant() === $this) {
                $video->setProductVariant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getOrderProductVariants(): Collection
    {
        return $this->orderProductVariants;
    }

    public function addOrderProductVariant(PurchaseProductVariant $orderProductVariant): self
    {
        if (!$this->orderProductVariants->contains($orderProductVariant)) {
            $this->orderProductVariants[] = $orderProductVariant;
            $orderProductVariant->setProductVariant($this);
        }

        return $this;
    }

    public function removeOrderProductVariant(PurchaseProductVariant $orderProductVariant): self
    {
        if ($this->orderProductVariants->removeElement($orderProductVariant)) {
            // set the owning side to null (unless already changed)
            if ($orderProductVariant->getProductVariant() === $this) {
                $orderProductVariant->setProductVariant(null);
            }
        }

        return $this;
    }


    public function getAvailability(): ?Availability
    {
        return $this->availability;
    }

    public function setAvailability(?Availability $availability): self
    {
        $this->availability = $availability;

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
            $parameter->setProductVariant($this);
        }

        return $this;
    }

    public function removeParameter(Parameter $parameter): self
    {
        if ($this->parameters->removeElement($parameter)) {
            // set the owning side to null (unless already changed)
            if ($parameter->getProductVariant() === $this) {
                $parameter->setProductVariant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Price>
     */
    public function getPrice(): Collection
    {
        return $this->price;
    }

    public function addPrice(Price $price): self
    {
        if (!$this->price->contains($price)) {
            $this->price->add($price);
            $price->setProductVariant($this);
        }

        return $this;
    }

    public function removePrice(Price $price): self
    {
        if ($this->price->removeElement($price)) {
            // set the owning side to null (unless already changed)
            if ($price->getProductVariant() === $this) {
                $price->setProductVariant(null);
            }
        }

        return $this;
    }

    public function getAvgRestockDays(): ?int
    {
        return $this->AvgRestockDays;
    }

    public function setAvgRestockDays(?int $AvgRestockDays): self
    {
        $this->AvgRestockDays = $AvgRestockDays;

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
    public function getProductVariantUploadGroups(): Collection
    {
        return $this->productVariantUploadGroups;
    }

    public function addProductVariantUploadGroup(ProductVariantUploadGroup $productVariantUploadGroup): self
    {
        if (!$this->productVariantUploadGroups->contains($productVariantUploadGroup)) {
            $this->productVariantUploadGroups->add($productVariantUploadGroup);
            $productVariantUploadGroup->setProductVariant($this);
        }

        return $this;
    }

    public function removeProductVariantUploadGroup(ProductVariantUploadGroup $productVariantUploadGroup): self
    {
        if ($this->productVariantUploadGroups->removeElement($productVariantUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($productVariantUploadGroup->getProductVariant() === $this) {
                $productVariantUploadGroup->setProductVariant(null);
            }
        }

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }
}
