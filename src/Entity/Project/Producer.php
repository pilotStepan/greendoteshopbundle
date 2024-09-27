<?php

namespace Greendot\EshopBundle\Entity\Project;


use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\ApiResource\ProducerSearchFilter;
use Greendot\EshopBundle\Repository\Project\ProducerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;


#[ApiFilter(ProducerSearchFilter::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['producer_info:read']],
    paginationClientItemsPerPage: true
)]
#[ORM\Entity(repositoryClass: ProducerRepository::class)]
class Producer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_info:read', 'producer_info:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['producer_info:read','product_info:read', 'product_info:write', 'search_result'])]
    private $name;

    #[Groups(['producer_info:read'])]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $menu_name;

    #[Groups(['producer_info:read'])]
    #[ORM\Column(type: 'string', length: 255)]
    private $title;

    #[Groups(['producer_info:read'])]
    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'text', nullable: true)]
    private $html;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private $is_menu;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'producer')]
    private $Product;

    #[Groups(['product_info:read', 'producer_info:read','product_info:write', 'search_result'])]
    #[ORM\ManyToOne(inversedBy: 'producers')]
    private ?Upload $upload = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    public function __construct()
    {
        $this->Product = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getIsMenu(): ?bool
    {
        return $this->is_menu;
    }

    public function setIsMenu(bool $is_menu): self
    {
        $this->is_menu = $is_menu;

        return $this;
    }

    /**
     * @return Collection|Product[]
     */
    public function getProduct(): Collection
    {
        return $this->Product;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->Product->contains($product)) {
            $this->Product[] = $product;
            $product->setProducer($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        if ($this->Product->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getProducer() === $this) {
                $product->setProducer(null);
            }
        }

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
