<?php

namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Greendot\EshopBundle\ApiResource\BoundingBoxFilter;
use Greendot\EshopBundle\Repository\Project\BranchRepository;

#[ORM\Entity(repositoryClass: BranchRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['branch:read']],
    denormalizationContext: ['groups' => ['branch:write']],
    paginationEnabled: true
)]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'country' => 'exact', 'transportation' => 'exact', 'BranchType.id' => 'exact', 'transportation.groups.id' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(BoundingBoxFilter::class)]
class Branch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['branch:read', 'branch:write', 'purchase:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $street = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $zip = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $country = null;

    #[ORM\Column(length: 512, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $lat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 6, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $lng = null;

    #[ORM\Column(name: 'provider_id', type: 'string', length: 64)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $provider_id = null;

    #[ORM\ManyToOne(targetEntity: BranchType::class, cascade: ['persist'], inversedBy: 'Branch')]
    #[Groups(['branch:read', 'branch:write'])]
    private ?BranchType $BranchType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?bool $is_active = null;

    /**
     * @var Collection<int, BranchOpeningHours>
     */
    #[ORM\OneToMany(targetEntity: BranchOpeningHours::class, mappedBy: 'branch', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $BranchOpeningHours;

    #[ORM\ManyToOne(targetEntity: Transportation::class, inversedBy: 'branches')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?Transportation $transportation = null;

    /**
     * @var Collection<int, Purchase>
     */
    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'branch')]
    private Collection $Purchases;


    public function __construct()
    {
        $this->BranchOpeningHours = new ArrayCollection();
        $this->Purchases = new ArrayCollection();
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

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(?string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLat(): ?string
    {
        return $this->lat;
    }

    public function setLat(?string $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLng(): ?string
    {
        return $this->lng;
    }

    public function setLng(?string $lng): static
    {
        $this->lng = $lng;

        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->provider_id;
    }

    public function setProviderId(string $provider_id): static
    {
        $this->provider_id = $provider_id;

        return $this;
    }

    public function getBranchType(): ?BranchType
    {
        return $this->BranchType;
    }

    public function setBranchType(?BranchType $BranchType): static
    {
        $this->BranchType = $BranchType;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setActive(?bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    /**
     * @return Collection<int, BranchOpeningHours>
     */
    public function getBranchOpeningHours(): Collection
    {
        return $this->BranchOpeningHours;
    }

    public function addBranchOpeningHour(BranchOpeningHours $branchOpeningHour): static
    {
        if (!$this->BranchOpeningHours->contains($branchOpeningHour)) {
            $this->BranchOpeningHours->add($branchOpeningHour);
            $branchOpeningHour->setBranch($this);
        }

        return $this;
    }

    public function removeBranchOpeningHour(BranchOpeningHours $branchOpeningHour): static
    {
        if ($this->BranchOpeningHours->removeElement($branchOpeningHour)) {
            // set the owning side to null (unless already changed)
            if ($branchOpeningHour->getBranch() === $this) {
                $branchOpeningHour->setBranch(null);
            }
        }

        return $this;
    }

    public function getTransportation(): ?Transportation
    {
        return $this->transportation;
    }

    public function setTransportation(?Transportation $transportation): static
    {
        $this->transportation = $transportation;

        return $this;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function getPurchases(): Collection
    {
        return $this->Purchases;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->Purchases->contains($purchase)) {
            $this->Purchases->add($purchase);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        $this->Purchases->removeElement($purchase);

        return $this;
    }

    #[Groups(['branch:read', 'purchase:read'])]
    public function getTextAddress(): string
    {
        $address = array_filter([
            $this->name,
            $this->street,
            $this->city,
            $this->zip,
            $this->country,
        ]);
        return implode(", ", $address);
    }
}
