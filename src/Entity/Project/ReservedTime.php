<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\ReservedTimeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReservedTimeRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['reservedTime:read']],
    denormalizationContext: ['groups' => ['reservedTime:write']],
    order: ['startTime' => 'ASC']
)]
#[ApiFilter(DateFilter::class, properties: ['startTime'])]
class ReservedTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['reservedTime:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['reservedTime:read', 'reservedTime:write'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['reservedTime:read', 'reservedTime:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['reservedTime:read', 'reservedTime:write'])]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['reservedTime:read', 'reservedTime:write'])]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['reservedTime:read', 'reservedTime:write'])]
    private ?int $type = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): self
    {
        $this->type = $type;

        return $this;
    }
}
