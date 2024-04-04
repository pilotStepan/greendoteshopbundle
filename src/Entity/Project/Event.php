<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['event:read']],
    denormalizationContext: ['groups' => ['event:write']],
    order: ['dateStart' => 'DESC']
)]
#[ApiFilter(DateFilter::class, properties: ['dateStart', 'dateEnd'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read', 'event:write'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    private ?\DateTimeInterface $dateStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    private ?\DateTimeInterface $dateEnd = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    private ?string $html = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['event:read', 'event:write'])]
    private ?float $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $state = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: CategoryEvent::class)]
    private Collection $categories;

    #[ORM\Column(length: 255)]
    #[Groups(['event:read', 'event:write'])]
    private ?string $slug = null;
    #[ORM\ManyToOne(inversedBy: 'event')]
    private ?EventType $eventType = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: PurchaseEvent::class)]
    private Collection $purchaseEvents;

    #[ORM\Column(options: ["default" => 0])]
    private ?bool $isActive = null;

    #[ORM\Column(options: ["default" => 0])]
    private ?bool $isDeleted = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->purchaseEvents = new ArrayCollection();
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

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }

    public function setDateStart(\DateTimeInterface $dateStart): self
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateEnd;
    }

    public function setDateEnd(\DateTimeInterface $dateEnd): self
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return Collection<int, CategoryEvent>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(CategoryEvent $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setEvent($this);
        }

        return $this;
    }

    public function removeCategory(CategoryEvent $category): self
    {
        if ($this->categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getEvent() === $this) {
                $category->setEvent(null);
            }
        }

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

    public function getEventType(): ?EventType
    {
        return $this->eventType;
    }

    public function setEventType(?EventType $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return Collection<int, PurchaseEvent>
     */
    public function getPurchaseEvents(): Collection
    {
        return $this->purchaseEvents;
    }

    public function addPurchaseEvent(PurchaseEvent $purchaseEvent): self
    {
        if (!$this->purchaseEvents->contains($purchaseEvent)) {
            $this->purchaseEvents->add($purchaseEvent);
            $purchaseEvent->setEvent($this);
        }

        return $this;
    }

    public function removePurchaseEvent(PurchaseEvent $purchaseEvent): self
    {
        if ($this->purchaseEvents->removeElement($purchaseEvent)) {
            // set the owning side to null (unless already changed)
            if ($purchaseEvent->getEvent() === $this) {
                $purchaseEvent->setEvent(null);
            }
        }

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

}
