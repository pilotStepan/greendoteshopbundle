<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Entity\Project\HandlingPrice;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Locale;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: TransportationRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['transportation:read']],
    denormalizationContext: ['groups' => ['transportation:write']],
)]
class Transportation implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $id;

    /*
     * TODO fix typo
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Gedmo\Translatable]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $Name;

    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'Transportation')]
    #[Groups(['transportation:read', 'transportation:write'])]
    private $purchases;

    #[ORM\Column(type: 'text')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $description;

    #[ORM\Column(type: 'text')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $description_mail;

    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $description_duration;

    #[ORM\Column(type: 'text')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $html;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $icon;

    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $duration;

    /*
     * TODO fix typo
     */
    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $squence;


    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $country;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $state_url;

    #[ORM\Column(type: 'integer')]
    #[Groups(['transportation:read', 'transportation:write', 'purchase:read', 'purchase:write'])]
    private $section;

    #[ORM\ManyToMany(targetEntity: PaymentType::class, inversedBy: 'transportations')]
    private Collection $paymentTypes;

    #[ORM\Column(nullable: true)]
    private ?bool $isEnabled = null;

    #[ORM\Column(nullable: false)]
    private ?int $vat = null;

    #[Gedmo\Locale]
    private $locale;

    /**
     * @var Collection<int, HandlingPrice>
     */
    #[ORM\OneToMany(mappedBy: 'paymentType', targetEntity: HandlingPrice::class)]
    private Collection $handlingPrices;


    public function __construct()
    {
        $this->purchases = new ArrayCollection();
        $this->paymentTypes = new ArrayCollection();
        $this->handlingPrices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(string $Name): self
    {
        $this->Name = $Name;

        return $this;
    }

    /**
     * @return Collection|Purchase[]
     */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Purchase $purchase): self
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases[] = $purchase;
            $purchase->setTransportation($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): self
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getTransportation() === $this) {
                $purchase->setTransportation(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionMail(): ?string
    {
        return $this->description_mail;
    }

    public function setDescriptionMail(string $description_mail): self
    {
        $this->description_mail = $description_mail;

        return $this;
    }

    public function getDescriptionDuration(): ?int
    {
        return $this->description_duration;
    }

    public function setDescriptionDuration(int $description_duration): self
    {
        $this->description_duration = $description_duration;

        return $this;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function setHtml(string $html): self
    {
        $this->html = $html;

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

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getSquence(): ?int
    {
        return $this->squence;
    }

    public function setSquence(int $squence): self
    {
        $this->squence = $squence;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getStateUrl(): ?string
    {
        return $this->state_url;
    }

    public function setStateUrl(string $state_url): self
    {
        $this->state_url = $state_url;

        return $this;
    }

    public function getSection(): ?int
    {
        return $this->section;
    }

    public function setSection(int $section): self
    {
        $this->section = $section;

        return $this;
    }

    /**
     * @return Collection<int, PaymentType>
     */
    public function getPaymentTypes(): Collection
    {
        return $this->paymentTypes;
    }

    public function addPaymentType(PaymentType $payment): self
    {
        if (!$this->paymentTypes->contains($payment)) {
            $this->paymentTypes->add($payment);
        }

        return $this;
    }

    public function removePaymentType(PaymentType $paymentType): self
    {
        $this->paymentTypes->removeElement($paymentType);

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getVat(): ?int
    {
        return $this->vat;
    }

    public function setVat(?int $vat): self
    {
        $this->vat = $vat;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }


    /**
     * @return Collection<int, HandlingPrice>
     */
    public function getHandlingPrices(): Collection
    {
        return $this->handlingPrices;
    }

    public function addHandlingPrice(HandlingPrice $handlingPrice): static
    {
        if (!$this->handlingPrices->contains($handlingPrice)) {
            $this->handlingPrices->add($handlingPrice);
            $handlingPrice->setPaymentType($this);
        }

        return $this;
    }

    public function removeHandlingPrice(HandlingPrice $handlingPrice): static
    {
        if ($this->handlingPrices->removeElement($handlingPrice)) {
            // set the owning side to null (unless already changed)
            if ($handlingPrice->getPaymentType() === $this) {
                $handlingPrice->setPaymentType(null);
            }
        }

        return $this;
    }

}
