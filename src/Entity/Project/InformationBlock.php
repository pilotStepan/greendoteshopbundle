<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Greendot\EshopBundle\Repository\Project\InformationBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InformationBlockRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['informationBlock:read']],
    denormalizationContext: ['groups' => ['InformationBlock:write']],
)]
class InformationBlock implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_item:read', 'informationBlock:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Translatable]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product_item:read', 'informationBlock:read'])]
    #[Gedmo\Translatable]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product_item:read', 'informationBlock:read'])]
    #[Gedmo\Translatable]
    private ?string $text = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\ManyToOne(inversedBy: 'informationBlocks')]
    #[Groups(['product_item:read'])]
    private ?InformationBlockType $informationBlockType = null;

    #[ORM\OneToMany(mappedBy: 'informationBlock', targetEntity: CategoryInformationBlock::class)]
    private Collection $categoryInformationBlocks;

    #[ORM\OneToMany(mappedBy: 'informationBlock', targetEntity: ProductInformationBlock::class)]
    private Collection $productInformationBlocks;

    #[ORM\OneToMany(mappedBy: 'informationBlock', targetEntity: EventInformationBlock::class)]
    private Collection $eventInformationBlocks;

    #[ORM\OneToMany(mappedBy: 'informationBlock', targetEntity: PersonInformationBlock::class)]
    private Collection $personInformationBlocks;

    #[ORM\Column(options: ["default" => 0])]
    private ?bool $isReusable = null;

    #[Gedmo\Locale]
    private $locale;

    #[ORM\ManyToOne(inversedBy: 'informationBlocks')]
    private ?Upload $upload = null;

    #[ORM\OneToMany(mappedBy: 'informationBlock', targetEntity: InformationBlockUploadGroup::class, cascade: ['persist'])]
    private Collection $informationBlockUploadGroups;

    public function __construct()
    {
        $this->categoryInformationBlocks = new ArrayCollection();
        $this->productInformationBlocks = new ArrayCollection();
        $this->eventInformationBlocks = new ArrayCollection();
        $this->personInformationBlocks = new ArrayCollection();
        $this->informationBlockUploadGroups = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getInformationBlockType(): ?InformationBlockType
    {
        return $this->informationBlockType;
    }

    public function setInformationBlockType(?InformationBlockType $informationBlockType): static
    {
        $this->informationBlockType = $informationBlockType;

        return $this;
    }

    /**
     * @return Collection<int, CategoryInformationBlock>
     */
    public function getCategoryInformationBlocks(): Collection
    {
        return $this->categoryInformationBlocks;
    }

    public function addCategoryInformationBlock(CategoryInformationBlock $categoryInformationBlock): static
    {
        if (!$this->categoryInformationBlocks->contains($categoryInformationBlock)) {
            $this->categoryInformationBlocks->add($categoryInformationBlock);
            $categoryInformationBlock->setInformationBlock($this);
        }

        return $this;
    }

    public function removeCategoryInformationBlock(CategoryInformationBlock $categoryInformationBlock): static
    {
        if ($this->categoryInformationBlocks->removeElement($categoryInformationBlock)) {
            // set the owning side to null (unless already changed)
            if ($categoryInformationBlock->getInformationBlock() === $this) {
                $categoryInformationBlock->setInformationBlock(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductInformationBlock>
     */
    public function getProductInformationBlocks(): Collection
    {
        return $this->productInformationBlocks;
    }

    public function addProductInformationBlock(ProductInformationBlock $productInformationBlock): static
    {
        if (!$this->productInformationBlocks->contains($productInformationBlock)) {
            $this->productInformationBlocks->add($productInformationBlock);
            $productInformationBlock->setInformationBlock($this);
        }

        return $this;
    }

    public function removeProductInformationBlock(ProductInformationBlock $productInformationBlock): static
    {
        if ($this->productInformationBlocks->removeElement($productInformationBlock)) {
            // set the owning side to null (unless already changed)
            if ($productInformationBlock->getInformationBlock() === $this) {
                $productInformationBlock->setInformationBlock(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, EventInformationBlock>
     */
    public function getEventInformationBlocks(): Collection
    {
        return $this->eventInformationBlocks;
    }

    public function addEventInformationBlock(EventInformationBlock $eventInformationBlock): static
    {
        if (!$this->eventInformationBlocks->contains($eventInformationBlock)) {
            $this->eventInformationBlocks->add($eventInformationBlock);
            $eventInformationBlock->setInformationBlock($this);
        }

        return $this;
    }

    public function removeEventInformationBlock(EventInformationBlock $eventInformationBlock): static
    {
        if ($this->eventInformationBlocks->removeElement($eventInformationBlock)) {
            // set the owning side to null (unless already changed)
            if ($eventInformationBlock->getInformationBlock() === $this) {
                $eventInformationBlock->setInformationBlock(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PersonInformationBlock>
     */
    public function getPersonInformationBlocks(): Collection
    {
        return $this->personInformationBlocks;
    }

    public function addPersonInformationBlock(PersonInformationBlock $personInformationBlock): static
    {
        if (!$this->personInformationBlocks->contains($personInformationBlock)) {
            $this->personInformationBlocks->add($personInformationBlock);
            $personInformationBlock->setInformationBlock($this);
        }

        return $this;
    }

    public function removePersonInformationBlock(PersonInformationBlock $personInformationBlock): static
    {
        if ($this->personInformationBlocks->removeElement($personInformationBlock)) {
            // set the owning side to null (unless already changed)
            if ($personInformationBlock->getInformationBlock() === $this) {
                $personInformationBlock->setInformationBlock(null);
            }
        }

        return $this;
    }

    public function isIsReusable(): ?bool
    {
        return $this->isReusable;
    }

    public function setIsReusable(bool $isReusable): static
    {
        $this->isReusable = $isReusable;

        return $this;
    }

    public function setTranslatableLocale($locale): void
    {
        $this->locale = $locale;
    }


    public function getUpload(): ?Upload
    {
        return $this->upload;
    }

    public function setUpload(?Upload $upload): static
    {
        $this->upload = $upload;

        return $this;
    }

    /**
     * @return Collection<int, InformationBlockUploadGroup>
     */
    public function getInformationBlockUploadGroups(): Collection
    {
        return $this->informationBlockUploadGroups;
    }

    public function addInformationBlockUploadGroup(InformationBlockUploadGroup $informationBlockUploadGroup): static
    {
        if (!$this->informationBlockUploadGroups->contains($informationBlockUploadGroup)) {
            $this->informationBlockUploadGroups->add($informationBlockUploadGroup);
            $informationBlockUploadGroup->setInformationBlock($this);
        }

        return $this;
    }

    public function removeInformationBlockUploadGroup(InformationBlockUploadGroup $informationBlockUploadGroup): static
    {
        if ($this->informationBlockUploadGroups->removeElement($informationBlockUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($informationBlockUploadGroup->getInformationBlock() === $this) {
                $informationBlockUploadGroup->setInformationBlock(null);
            }
        }

        return $this;
    }
}
