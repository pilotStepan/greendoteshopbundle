<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\PaymentActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PaymentActionRepository::class)]
#[ApiResource(
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

    /* @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'action')]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
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

    /**
     * @return Collection|Payment[]
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments[] = $payment;
            $payment->setAction($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getAction() === $this) {
                $payment->setAction(null);
            }
        }

        return $this;
    }
}