<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Greendot\EshopBundle\StateProvider\CheapTransportationStateProvider;
use Greendot\EshopBundle\StateProvider\PurchaseStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TransportationRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['transportation:read']],
    denormalizationContext: ['groups' => ['transportation:write']],
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: '/transportations/cheap',
            provider: CheapTransportationStateProvider::class,
        ),
        new Get(),
        new Post(),
        new Put(),
        new Delete(),
        new Patch(),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['action' => "exact"])]
class Transportation implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write', 'branch:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Gedmo\Translatable]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write', 'branch:read'])]
    private $name;

    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'Transportation')]
    private $purchases;

    #[ORM\Column(type: 'text')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $description;

    #[ORM\Column(type: 'text')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $description_mail;

    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $description_duration;

    #[ORM\Column(type: 'text')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $html;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $icon;

    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $duration;

    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $squence;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $country;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $state_url;

    #[ORM\ManyToMany(targetEntity: PaymentType::class, inversedBy: 'transportations')]
    #[Groups(['transportation:read', 'purchase:read'])]
    private Collection $paymentTypes;

    #[ORM\Column(nullable: true)]
    #[Groups(['transportation:read'])]
    private ?bool $isEnabled = null;

    #[Gedmo\Locale]
    private $locale;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $secretKey = null;

    #[Groups(['transportation:read'])]
    private $free_from_price;

    #[Groups(['transportation:read'])]
    private $price;

    /**
     * @var Collection<int, HandlingPrice>
     */
    #[ORM\OneToMany(targetEntity: HandlingPrice::class, mappedBy: 'transportation')]
    #[Groups(['transportation:read', 'transportation_group:read'])]
    private Collection $handlingPrices;

    #[ORM\ManyToOne(targetEntity: TransportationAction::class, cascade: ['persist'], inversedBy: 'transportations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transportation:read', 'purchase:read'])]
    private ?TransportationAction $action;

    /**
     * @var Collection<int, TransportationGroup>
     */
    #[ORM\ManyToMany(targetEntity: TransportationGroup::class, inversedBy: 'transportations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['transportation:read', 'purchase:read'])]
    private Collection $groups;

    /**
     * @var Collection<int, Branch>
     */
    #[ORM\OneToMany(targetEntity: Branch::class, mappedBy: 'transportation', orphanRemoval: true)]
    private Collection $branches;

    public function __construct()
    {
        $this->purchases      = new ArrayCollection();
        $this->paymentTypes   = new ArrayCollection();
        $this->handlingPrices = new ArrayCollection();
        $this->branches = new ArrayCollection();
        $this->groups = new ArrayCollection();
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
     * @return Collection|Purchase[]
     */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Purchase $purchase): self
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases[] = $purchase;
            $purchase->setTransportation($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): self
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getTransportation() === $this) {
                $purchase->setTransportation(null);
            }
        }

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

    public function getDescriptionMail(): ?string
    {
        return $this->description_mail;
    }

    public function setDescriptionMail(string $description_mail): self
    {
        $this->description_mail = $description_mail;

        return $this;
    }

    public function getDescriptionDuration(): ?int
    {
        return $this->description_duration;
    }

    public function setDescriptionDuration(int $description_duration): self
    {
        $this->description_duration = $description_duration;

        return $this;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function setHtml(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getSquence(): ?int
    {
        return $this->squence;
    }

    public function setSquence(int $squence): self
    {
        $this->squence = $squence;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getStateUrl(): ?string
    {
        return $this->state_url;
    }

    public function setStateUrl(string $state_url): self
    {
        $this->state_url = $state_url;

        return $this;
    }

    /**
     * @return Collection<int, PaymentType>
     */
    public function getPaymentTypes(): Collection
    {
        return $this->paymentTypes;
    }

    public function addPaymentType(PaymentType $payment): self
    {
        if (!$this->paymentTypes->contains($payment)) {
            $this->paymentTypes->add($payment);
        }

        return $this;
    }

    public function removePaymentType(PaymentType $paymentType): self
    {
        $this->paymentTypes->removeElement($paymentType);

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }



    public function getAction(): ?TransportationAction
    {
        return $this->action;
    }

    public function setAction(?TransportationAction $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @return Collection<int, TransportationGroup>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(TransportationGroup $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->addTransportation($this);
        }

        return $this;
    }

    public function removeGroup(TransportationGroup $group): static
    {
        if ($this->groups->removeElement($group)) {
            $group->removeTransportation($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, HandlingPrice>
     */
    public function getHandlingPrices(): Collection
    {
        return $this->handlingPrices;
    }

    public function addHandlingPrice(HandlingPrice $handlingPrice): static
    {
        if (!$this->handlingPrices->contains($handlingPrice)) {
            $this->handlingPrices->add($handlingPrice);
            $handlingPrice->setTransportation($this);
        }

        return $this;
    }

    public function removeHandlingPrice(HandlingPrice $handlingPrice): static
    {
        if ($this->handlingPrices->removeElement($handlingPrice)) {
            // set the owning side to null (unless already changed)
            if ($handlingPrice->getTransportation() === $this) {
                $handlingPrice->setTransportation(null);
            }
        }

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function setSecretKey(?string $secretKey): void
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function getBranches(): Collection
    {
        return $this->branches;
    }

    public function addBranch(Branch $branch): static
    {
        if (!$this->branches->contains($branch)) {
            $this->branches->add($branch);
            $branch->setTransportation($this);
        }

        return $this;
    }

    public function removeBranch(Branch $branch): static
    {
        if ($this->branches->removeElement($branch)) {
            // set the owning side to null (unless already changed)
            if ($branch->getTransportation() === $this) {
                $branch->setTransportation(null);
            }
        }

        return $this;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice($price): void
    {
        $this->price = $price;
    }

    /**
     * @return float
     */
    public function getFreeFromPrice()
    {
        return $this->free_from_price;
    }

    /**
     * @param float $free_from_price
     */
    public function setFreeFromPrice($free_from_price): void
    {
        $this->free_from_price = $free_from_price;
    }
}
