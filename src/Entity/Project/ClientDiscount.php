<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Repository\Project\ClientDiscountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;

/**
 *
 */
#[ORM\Entity(repositoryClass: ClientDiscountRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['client_discount:read']],
    denormalizationContext: ['groups' => ['client_discount:write']],
    paginationEnabled: false
)]
class ClientDiscount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['purchase:read'])]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['purchase:read'])]
    private ?float $discount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEnd = null;

    /**
     * @var Client|null
     * Links to the Client in case it is defined only for specified client. Otherwise it should be null.
     */
    #[ORM\ManyToOne(inversedBy: 'clientDiscounts')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Client $client = null;

    /**
     * @var Collection|ArrayCollection
     * Purchases during which the coupon was used.
     */
    #[ORM\OneToMany(mappedBy: 'clientDiscount', targetEntity: Purchase::class)]
    private Collection $purchase;

    /**
     * @var DiscountType
     * Discount type for the Coupon from ENUM.
     */
    #[ORM\Column(type: "string", enumType: DiscountType::class)]
    private DiscountType $type;

    #[ORM\Column(length: 255)]
    #[Groups(['purchase:read'])]
    #[ApiProperty(identifier: true)]
    private ?string $hash = null;


    /**
     * @var bool|null
     * Defines the validity of the coupon in case it is only SINGLE_USE DiscountType
     */
    #[ORM\Column]
    private ?bool $is_used = null;

    public function __construct()
    {
        $this->purchase = new ArrayCollection();
        $this->type = DiscountType::SingleUse;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }

    public function setDateStart(\DateTimeInterface $dateStart): self
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?\DateTimeInterface $dateEnd): self
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function getPurchase(): Collection
    {
        return $this->purchase;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->purchase->contains($purchase)) {
            $this->purchase->add($purchase);
            $purchase->setClientDiscount($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchase->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getClientDiscount() === $this) {
                $purchase->setClientDiscount(null);
            }
        }

        return $this;
    }

    public function isIsUsed(): ?bool
    {
        return $this->is_used;
    }

    public function setIsUsed(bool $is_used): static
    {
        $this->is_used = $is_used;

        return $this;
    }

    public function getType(): DiscountType
    {
        return $this->type;
    }

    public function setType(DiscountType $type): static
    {
        $this->type = $type;

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
}
