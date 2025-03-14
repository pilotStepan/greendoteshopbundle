<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\UploadGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UploadGroupRepository::class)]
class UploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'uploadGroup', targetEntity: Upload::class)]
    #[Groups(['category_default', 'category:read', 'category:write'])]
    private Collection $upload;

    #[ORM\Column(nullable: true)]
    #[Groups(['upload:read'])]
    private ?int $type = null;

    #[ORM\OneToMany(mappedBy: 'UploadGroup', targetEntity: CategoryUploadGroup::class)]
    private Collection $categoryUploadGroups;

    #[ORM\OneToMany(mappedBy: 'UploadGroup', targetEntity: PersonUploadGroup::class)]
    private Collection $personUploadGroups;

    #[ORM\OneToMany(mappedBy: 'UploadGroup', targetEntity: ProductVariantUploadGroup::class)]
    private Collection $productVariantUploadGroups;

    #[ORM\OneToMany(mappedBy: 'UploadGroup', targetEntity: ProductUploadGroup::class)]
    private Collection $productUploadGroup;

    #[ORM\OneToMany(mappedBy: 'UploadGroup', targetEntity: EventUploadGroup::class)]
    private Collection $eventUploadGroups;

    public function __construct()
    {
        $this->upload = new ArrayCollection();
        $this->categoryUploadGroups = new ArrayCollection();
        $this->personUploadGroups = new ArrayCollection();
        $this->productVariantUploadGroups = new ArrayCollection();
        $this->productUploadGroup = new ArrayCollection();
        $this->eventUploadGroups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Upload>
     */
    public function getUpload(): Collection
    {
        return $this->upload;
    }

    public function addUpload(Upload $upload): self
    {
        if (!$this->upload->contains($upload)) {
            $this->upload->add($upload);
            $upload->setUploadGroup($this);
        }

        return $this;
    }

    public function removeUpload(Upload $upload): self
    {
        if ($this->upload->removeElement($upload)) {
            // set the owning side to null (unless already changed)
            if ($upload->getUploadGroup() === $this) {
                $upload->setUploadGroup(null);
            }
        }

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): self
    {
        $this->type = $type;

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
            $categoryUploadGroup->setUploadGroup($this);
        }

        return $this;
    }

    public function removeCategoryUploadGroup(CategoryUploadGroup $categoryUploadGroup): self
    {
        if ($this->categoryUploadGroups->removeElement($categoryUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($categoryUploadGroup->getUploadGroup() === $this) {
                $categoryUploadGroup->setUploadGroup(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PersonUploadGroup>
     */
    public function getPersonUploadGroups(): Collection
    {
        return $this->personUploadGroups;
    }

    public function addPersonUploadGroup(PersonUploadGroup $personUploadGroup): self
    {
        if (!$this->personUploadGroups->contains($personUploadGroup)) {
            $this->personUploadGroups->add($personUploadGroup);
            $personUploadGroup->setUploadGroup($this);
        }

        return $this;
    }

    public function removePersonUploadGroup(PersonUploadGroup $personUploadGroup): self
    {
        if ($this->personUploadGroups->removeElement($personUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($personUploadGroup->getUploadGroup() === $this) {
                $personUploadGroup->setUploadGroup(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductUploadGroup>
     */
    public function getProductUploadGroups(): Collection
    {
        return $this->productUploadGroup;
    }

    public function addProductUploadGroup(ProductUploadGroup $productVariantUploadGroups): self
    {
        if (!$this->productUploadGroup->contains($productVariantUploadGroups)) {
            $this->productVariantUploadGroups->add($productVariantUploadGroups);
            $productVariantUploadGroups->setUploadGroup($this);
        }

        return $this;
    }

    public function removeProductUploadGroup(ProductUploadGroup $productVariantUploadGroups): self
    {
        if ($this->productUploadGroup->removeElement($productVariantUploadGroups)) {
            // set the owning side to null (unless already changed)
            if ($productVariantUploadGroups->getUploadGroup() === $this) {
                $productVariantUploadGroups->setUploadGroup(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductVariantUploadGroup>
     */
    public function getProductVariantUploadGroups(): Collection
    {
        return $this->productVariantUploadGroups;
    }

    public function addProductVariantUploadGroup(ProductVariantUploadGroup $productVariantUploadGroups): self
    {
        if (!$this->productVariantUploadGroups->contains($productVariantUploadGroups)) {
            $this->productVariantUploadGroups->add($productVariantUploadGroups);
            $productVariantUploadGroups->setUploadGroup($this);
        }

        return $this;
    }

    public function removeProductVariantUploadGroup(ProductVariantUploadGroup $productVariantUploadGroups): self
    {
        if ($this->productVariantUploadGroups->removeElement($productVariantUploadGroups)) {
            // set the owning side to null (unless already changed)
            if ($productVariantUploadGroups->getUploadGroup() === $this) {
                $productVariantUploadGroups->setUploadGroup(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, EventUploadGroup>
     */
    public function getEventUploadGroups(): Collection
    {
        return $this->eventUploadGroups;
    }

    public function addEventUploadGroup(EventUploadGroup $eventUploadGroup): self
    {
        if (!$this->eventUploadGroups->contains($eventUploadGroup)) {
            $this->eventUploadGroups->add($eventUploadGroup);
            $eventUploadGroup->setUploadGroup($this);
        }

        return $this;
    }

    public function removeEventUploadGroup(EventUploadGroup $eventUploadGroup): self
    {
        if ($this->eventUploadGroups->removeElement($eventUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($eventUploadGroup->getUploadGroup() === $this) {
                $eventUploadGroup->setUploadGroup(null);
            }
        }

        return $this;
    }

}
