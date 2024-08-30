<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\TransportationActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TransportationActionRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['transportation_action:read']],
    denormalizationContext: ['groups' => ['transportation_action:write']],
)]
class TransportationAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation_action:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_action:read', 'transportation_action:write'])]
    private $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_action:read', 'transportation_action:write'])]
    private $icon;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation_action:read', 'transportation_action:write'])]
    private $country;

    #[ORM\OneToMany(targetEntity: Transportation::class, mappedBy: 'action')]
    #[Groups(['transportation_action:read'])]
    private $transportations;

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

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country): void
    {
        $this->country = $country;
    }

    /**
     * @return Collection|Transportation[]
     */
    public function getTransportations(): Collection
    {
        return $this->transportations;
    }

    public function addTransportation(Transportation $transportation): self
    {
        if (!$this->transportations->contains($transportation)) {
            $this->transportations[] = $transportation;
            $transportation->setAction($this);
        }
        return $this;
    }

    public function removeTransportation(Transportation $transportation): self
    {
        if ($this->transportations->removeElement($transportation)) {
            if ($transportation->getAction() === $this) {
                $transportation->setAction(null);
            }
        }
        return $this;
    }
}