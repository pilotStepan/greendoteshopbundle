<?php

namespace Greendot\EshopBundle\Entity\Project;

use DateTimeInterface;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Enum\ParcelDeliveryState;
use Greendot\EshopBundle\Repository\Project\TransportationEventRepository;

#[ORM\Entity(repositoryClass: TransportationEventRepository::class)]
#[ApiResource]
class TransportationEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $occurredAt = null; // When the carrier says it happened (nullable if unknown)

    #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable')]
    private DateTimeInterface $recordedAt; // When we stored it

    #[ORM\Column(type: "string", enumType: ParcelDeliveryState::class)]
    private ParcelDeliveryState $state;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'transportationEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private Purchase $purchase;

    public function __construct()
    {
        $this->recordedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): ?DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(?DateTimeInterface $occurredAt): TransportationEvent
    {
        $this->occurredAt = $occurredAt;
        return $this;
    }

    public function getRecordedAt(): DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function getState(): ParcelDeliveryState
    {
        return $this->state;
    }

    public function setState(ParcelDeliveryState $state): static
    {
        $this->state = $state;
        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): static
    {
        $this->details = $details;
        return $this;
    }

    public function getPurchase(): Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(Purchase $purchase): static
    {
        $this->purchase = $purchase;
        return $this;
    }
}
