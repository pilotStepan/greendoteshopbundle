<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: PaymentTypeRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['payment:read']],
    denormalizationContext: ['groups' => ['payment:write']],
)]
class PaymentType implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    #[Gedmo\Translatable]
    private $Name;

    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'PaymentType')]
    #[Groups(['payment:read', 'payment:write'])]
    private $purchases;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $description;

    #[ORM\Column(type: 'text')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $descrition_mail;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $description_duration;

    #[ORM\Column(type: 'text')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $html;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $icon;

    #[ORM\Column(type: 'integer')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $duration;

    #[ORM\Column(type: 'float')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $price;

    #[ORM\Column(type: 'float')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $free_from_price;

    #[ORM\Column(type: 'integer')]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $sequence;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['payment:read', 'payment:write', 'purchase:read', 'purchase:write'])]
    private $country;

    #[ORM\ManyToMany(targetEntity: Transportation::class, mappedBy: 'paymentTypes')]
    private Collection $transportations;

    #[ORM\Column(nullable: true)]
    private ?bool $isEnabled = null;

    #[ORM\Column(nullable: true)]
    private ?int $vat = null;

    public function __construct()
    {
        $this->purchases = new ArrayCollection();
        $this->transportations = new ArrayCollection();
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
            $purchase->setPaymentType($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): self
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getPaymentType() === $this) {
                $purchase->setPaymentType(null);
            }
        }

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

    public function getDescritionMail(): ?string
    {
        return $this->descrition_mail;
    }

    public function setDescritionMail(string $descrition_mail): self
    {
        $this->descrition_mail = $descrition_mail;

        return $this;
    }

    public function getDescriptionDuration(): ?string
    {
        return $this->description_duration;
    }

    public function setDescriptionDuration(string $description_duration): self
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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getFreeFromPrice(): ?float
    {
        return $this->free_from_price;
    }

    public function setFreeFromPrice(float $free_from_price): self
    {
        $this->free_from_price = $free_from_price;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

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

    /**
     * @return Collection<int, Transportation>
     */
    public function getTransportations(): Collection
    {
        return $this->transportations;
    }

    public function addTransportation(Transportation $transportation): self
    {
        if (!$this->transportations->contains($transportation)) {
            $this->transportations->add($transportation);
            $transportation->addPaymentType($this);
        }

        return $this;
    }

    public function removeTransportation(Transportation $transportation): self
    {
        if ($this->transportations->removeElement($transportation)) {
            $transportation->removePaymentType($this);
        }

        return $this;
    }

    public function isIsEnabled(): ?bool
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
}
