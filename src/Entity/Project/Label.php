<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Greendot\EshopBundle\Repository\Project\LabelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: 'p_label')]
#[ORM\Entity(repositoryClass: LabelRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['label:read']],
    denormalizationContext: ['groups' => ['label:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['labelType'=>'exact'])]
class Label implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['label:read', 'label:write', 'product_item:read', 'product_list:read', 'product_info:write'])]
    private $id;

    #[ORM\Column(type: 'string', length: 150)]
    #[Groups(['category:read', 'category:write', 'product_item:read', 'product_list:read', 'product_info:write'])]
    private $name;

    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'labels')]
    private $categories;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'labels')]
    private Collection $products;

    #[ORM\ManyToOne(inversedBy: 'labels')]
    #[ORM\JoinColumn(name: 'label_type_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['label:read', 'label:write', 'category:read', 'category:write', 'product_item:read', 'product_list:read', 'product_info:write'])]
    private ?LabelType $labelType = null;

    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    #[Gedmo\Locale]
    private $locale;
    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->products = new ArrayCollection();
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

    /**
     * @return Collection|Category[]
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories[] = $category;
            $category->addLabel($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->categories->removeElement($category)) {
            $category->removeLabel($this);
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getName();
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addLabel($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeLabel($this);
        }

        return $this;
    }

    public function getLabelType(): ?LabelType
    {
        return $this->labelType;
    }

    public function setLabelType(?LabelType $labelType): static
    {
        $this->labelType = $labelType;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->slug = $description;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(?int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }
}
