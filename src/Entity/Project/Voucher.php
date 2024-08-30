<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\VoucherRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 *  Gift certificate that can be bought on the eshop and used as equivalent to money value.
 */
#[ORM\Entity(repositoryClass: VoucherRepository::class)]
#[ApiResource]
class Voucher
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $hash = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_issued = null;


    /**
     * @var Purchase|null
     * Link to the purchase within which the certificate was bought and according to which state is linked its validity.
     */
    #[ORM\ManyToOne(inversedBy: 'vouchers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $Purchase_issued = null;

    /**
     * @var Purchase|null
     * Link to the purchase where the certificate was used for payment.
     */
    #[ORM\ManyToOne(inversedBy: 'vouchers')]
    private ?Purchase $Purchase_used = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['voucher:read', 'voucher:write'])]
    private ?string $state = "draft";

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_until = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getDateIssued(): ?\DateTimeInterface
    {
        return $this->date_issued;
    }

    public function setDateIssued(?\DateTimeInterface $date_issued): static
    {
        $this->date_issued = $date_issued;

        return $this;
    }

    public function getPurchaseIssued(): ?Purchase
    {
        return $this->Purchase_issued;
    }

    public function setPurchaseIssued(?Purchase $Purchase_issued): static
    {
        $this->Purchase_issued = $Purchase_issued;

        return $this;
    }

    public function getPurchaseUsed(): ?Purchase
    {
        return $this->Purchase_used;
    }

    public function setPurchaseUsed(?Purchase $Purchase_used): static
    {
        $this->Purchase_used = $Purchase_used;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDateUntil(): ?\DateTimeInterface
    {
        return $this->date_until;
    }

    public function setDateUntil(?\DateTimeInterface $date_until): static
    {
        $this->date_until = $date_until;

        return $this;
    }
}

