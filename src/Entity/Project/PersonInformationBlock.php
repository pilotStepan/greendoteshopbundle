<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\PersonInformationBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonInformationBlockRepository::class)]
class PersonInformationBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'personInformationBlocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InformationBlock $informationBlock = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $person = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInformationBlock(): ?InformationBlock
    {
        return $this->informationBlock;
    }

    public function setInformationBlock(?InformationBlock $informationBlock): static
    {
        $this->informationBlock = $informationBlock;

        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(?Person $person): static
    {
        $this->person = $person;

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
}
