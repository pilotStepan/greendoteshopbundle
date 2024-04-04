<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\PersonUploadGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonUploadGroupRepository::class)]
#[ApiResource]
class PersonUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'personUploadGroups')]
    private ?UploadGroup $UploadGroup = null;

    #[ORM\ManyToOne(inversedBy: 'personUploadGroups')]
    private ?Person $Person = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUploadGroup(): ?UploadGroup
    {
        return $this->UploadGroup;
    }

    public function setUploadGroup(?UploadGroup $UploadGroup): self
    {
        $this->UploadGroup = $UploadGroup;

        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->Person;
    }

    public function setPerson(?Person $Person): self
    {
        $this->Person = $Person;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }
}
