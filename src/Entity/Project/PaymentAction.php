<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\Repository\Project\PaymentActionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentActionRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['payment_action:read']],
    denormalizationContext: ['groups' => ['payment_action:write']],
)]
class PaymentAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['payment_action:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment_action:read', 'payment_action:write'])]
    private ?string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payment_action:read', 'payment_action:write'])]
    private ?string $data;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['payment_action:read', 'payment_action:write'])]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['payment_action:read', 'payment_action:write'])]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment_action:read', 'payment_action:write'])]
    private ?string $performed_by; // client, admin, system

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'paymentActions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment_action:read'])]
    private ?Purchase $purchase = null;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment_action:read'])]
    private ?Payment $payment = null;

    public function __construct()
    {
        $this->date = new \DateTime();
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

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPerformedBy(): ?string
    {
        return $this->performed_by;
    }

    public function setPerformedBy(string $performed_by): self
    {
        $this->performed_by = $performed_by;

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

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        $this->payment = $payment;

        return $this;
    }
}