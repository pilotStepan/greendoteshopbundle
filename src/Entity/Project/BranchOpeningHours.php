<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\BranchOpeningHoursRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BranchOpeningHoursRepository::class)]
class BranchOpeningHours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'BranchOpeningHours')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Branch $branch = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $day = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $openedFrom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $openedUntil = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $full_time = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getDay(): ?string
    {
        return $this->day;
    }

    public function setDay(?string $day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getOpenedFrom(): ?string
    {
        return $this->openedFrom;
    }

    public function setOpenedFrom(?string $openedFrom): static
    {
        $this->openedFrom = $openedFrom;

        return $this;
    }

    public function getOpenedUntil(): ?string
    {
        return $this->openedUntil;
    }

    public function setOpenedUntil(?string $openedUntil): static
    {
        $this->openedUntil = $openedUntil;

        return $this;
    }

    public function getFullTime(): ?string
    {
        return $this->full_time;
    }

    public function setFullTime(?string $full_time): static
    {
        $this->full_time = $full_time;

        return $this;
    }
}
