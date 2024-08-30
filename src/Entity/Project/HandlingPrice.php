<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: HandlingPriceRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['handlingPrice:read']],
    denormalizationContext: ['groups' => ['handlingPrice:write']],
)]
class HandlingPrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['transportation:read', 'payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['transportation:read', 'payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?float $price = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?float $free_from_price = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?float $discount = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?int $vat = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?DateTimeInterface $validUntil = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['payment:read', 'transportation_action:read', 'handlingPrice:read'])]
    private ?DateTimeInterface $created = null;

    #[ORM\ManyToOne(targetEntity: Transportation::class, inversedBy: 'handlingPrices')]
    #[Groups(['handlingPrice:read'])]
    private ?Transportation $transportation = null;

    #[ORM\ManyToOne(targetEntity: PaymentType::class, inversedBy: 'handlingPrices')]
    #[Groups(['handlingPrice:read'])]
    private ?PaymentType $paymentType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getFreeFromPrice(): ?float
    {
        return $this->free_from_price;
    }

    public function setFreeFromPrice(?float $free_from_price): static
    {
        $this->free_from_price = $free_from_price;

        return $this;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getValidFrom(): ?DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getCreated(): ?DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(?DateTimeInterface $created): static
    {
        $this->created = $created;

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

    public function getPaymentType(): ?PaymentType
    {
        return $this->paymentType;
    }

    public function setPaymentType(?PaymentType $paymentType): static
    {
        $this->paymentType = $paymentType;

        return $this;
    }

    public function setVat(?int $vat) : static
    {
        $this->vat = $vat;

        return $this;
    }

    public function getVat() : ?int
    {
        return $this->vat;
    }
}
