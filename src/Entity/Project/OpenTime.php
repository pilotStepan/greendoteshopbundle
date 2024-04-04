<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\OpenTimeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OpenTimeRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['openTimes:read']],
    denormalizationContext: ['groups' => ['openTimes:write']],
)]
class OpenTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['openTimes:read', 'openTimes:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['openTimes:read', 'openTimes:write'])]
    private ?string $dayName = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['openTimes:read', 'openTimes:write'])]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['openTimes:read', 'openTimes:write'])]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column]
    #[Groups(['openTimes:read', 'openTimes:write'])]
    private ?bool $isClosed = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDayName(): ?string
    {
        return $this->dayName;
    }

    public function setDayName(string $dayName): self
    {
        $this->dayName = $dayName;

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function isIsClosed(): ?bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(bool $isClosed): self
    {
        $this->isClosed = $isClosed;

        return $this;
    }
}
