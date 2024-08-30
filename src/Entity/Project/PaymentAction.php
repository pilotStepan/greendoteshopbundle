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
    private $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment_action:read', 'payment_action:write'])]
    private $icon;

    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'action')]
    private $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
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