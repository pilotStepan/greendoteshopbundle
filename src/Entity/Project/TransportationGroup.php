<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Repository\Project\TransportationGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TransportationGroupRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['transportation_group:read']],
    denormalizationContext: ['groups' => ['transportation_group:write']],
)]
class TransportationGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['transportation_group:read', 'transportation:read', 'purchase:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'purchase:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'purchase:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'purchase:read'])]
    private ?string $icon = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_group:read', 'transportation_group:write', 'transportation:read', 'purchase:read'])]
    private ?string $country;

    /**
     * @var Collection<int, Transportation>
     */
    #[ORM\ManyToMany(targetEntity: Transportation::class, mappedBy: 'groups')]
    #[Groups(['transportation_group:read'])]
    private Collection $transportations;

    public function __construct()
    {
        $this->transportations = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): TransportationGroup
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return Collection<int, Transportation>
     */
    public function getTransportations(): Collection
    {
        return $this->transportations;
    }

    public function addTransportation(Transportation $transportation): static
    {
        if (!$this->transportations->contains($transportation)) {
            $this->transportations->add($transportation);
        }

        return $this;
    }

    public function removeTransportation(Transportation $transportation): static
    {
        $this->transportations->removeElement($transportation);

        return $this;
    }
}
