<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 *
 * This entity is for storing payment activity for Purchase - eq each try for Card payment, each recorded bank account payment
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private $externalId;

    #[ORM\Column(type: 'datetime')]
    private $date;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private $purchase;

    #[ORM\ManyToOne(targetEntity: PaymentAction::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: true)]
    private $action;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?int
    {
        return $this->externalId;
    }

    public function setExternalId(int $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function setPurchase(?Purchase $purchase): self
    {
        $this->purchase = $purchase;

        return $this;
    }

    public function getAction(): ?PaymentAction
    {
        return $this->action;
    }

    public function setAction(?PaymentAction $action): self
    {
        $this->action = $action;
        return $this;
    }
}
