<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Entity\Project\ClientAddress;
use App\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\PurchaseTracking;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: '`purchase`')]
#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['purchase:read']],
    denormalizationContext: ['groups' => ['purchase:write']],
    order: ['date_issue' => 'DESC']
)]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'state' => 'exact', 'invoice_number' => 'exact','Client.name' => 'partial','Client.surname' => 'partial'])]
#[ApiFilter(ExistsFilter::class, properties: ['purchaseEvents', 'ProductVariant'])]
#[ApiFilter(DateFilter::class, properties: ['date_issue'])]

class Purchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:write', 'client:read', 'event_purchase'])]
    private $id;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['purchase:read', 'purchase:write', 'client:read', 'event_purchase'])]
    private $date_issue;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $date_expedition;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $date_invoiced;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['purchase:read', 'purchase:write', 'client:read', 'event_purchase'])]
    private $state = "draft";

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $invoice_number;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $is_exported_stock;

    #[ORM\Column(type: 'boolean',nullable: true, options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $is_exported_book;

    #[ORM\Column(type: 'boolean',nullable: true, options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $is_exported_transport;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $transport_number;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $client_number;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $review_type;

    #[ORM\ManyToOne(targetEntity: PaymentType::class, inversedBy: 'purchases')]
    #[Groups(['purchase:read', 'purchase:write', 'client:read', 'event_purchase'])]
    private $PaymentType;

    #[ORM\ManyToOne(targetEntity: Transportation::class, inversedBy: 'purchases')]
    #[Groups(['purchase:read', 'purchase:write', 'client:read', 'event_purchase'])]
    private $Transportation;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'purchases')]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private $Client;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseProductVariant::class, cascade: ['persist'])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private Collection $ProductVariants;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseEvent::class)]
    #[Groups(['purchase:read', 'purchase:write', 'client:read', 'event_purchase'])]
    private Collection $purchaseEvents;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: Note::class)]
    #[Groups(['purchase:read', 'purchase:write', 'client:read', 'event_purchase'])]
    private Collection $notes;

    #[ApiProperty]
    #[Groups(['purchase:read', 'purchase:write', 'client:read'])]
    private $purchasePrice;
    #[ApiProperty]
    #[Groups(['purchase:read', 'purchase:write', 'client:read'])]
    private $purchasePriceVat;

    #[ORM\ManyToOne(inversedBy: 'type')]
    private ?ClientDiscount $clientDiscount = null;

    #[ORM\ManyToOne(inversedBy: 'Purchase')]
    #[Groups(['purchase:read'])]
    private ?ClientAddress $clientAddress = null;

    public function __construct()
    {
        $this->ProductVariants = new ArrayCollection();
        $this->purchaseEvents = new ArrayCollection();
        $this->notes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateIssue(): ?\DateTimeInterface
    {
        return $this->date_issue;
    }

    public function setDateIssue(\DateTimeInterface $date_issue): self
    {
        $this->date_issue = $date_issue;

        return $this;
    }

    public function getDateExpedition(): ?\DateTimeInterface
    {
        return $this->date_expedition;
    }

    public function setDateExpedition(?\DateTimeInterface $date_expedition): self
    {
        $this->date_expedition = $date_expedition;

        return $this;
    }

    public function getDateInvoiced(): ?\DateTimeInterface
    {
        return $this->date_invoiced;
    }

    public function setDateInvoiced(?\DateTimeInterface $date_invoiced): self
    {
        $this->date_invoiced = $date_invoiced;

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

    public function getInvoiceNumber(): ?string
    {
        return $this->invoice_number;
    }

    public function setInvoiceNumber(string $invoice_number): self
    {
        $this->invoice_number = $invoice_number;

        return $this;
    }

    public function getIsExportedStock(): ?bool
    {
        return $this->is_exported_stock;
    }

    public function setIsExportedStock(bool $is_exported_stock): self
    {
        $this->is_exported_stock = $is_exported_stock;

        return $this;
    }

    public function getIsExportedBook(): ?bool
    {
        return $this->is_exported_book;
    }

    public function setIsExportedBook(bool $is_exported_book): self
    {
        $this->is_exported_book = $is_exported_book;

        return $this;
    }

    public function getIsExportedTransport(): ?bool
    {
        return $this->is_exported_transport;
    }

    public function setIsExportedTransport(bool $is_exported_transport): self
    {
        $this->is_exported_transport = $is_exported_transport;

        return $this;
    }

    public function getTransportNumber(): ?string
    {
        return $this->transport_number;
    }

    public function setTransportNumber(string $transport_number): self
    {
        $this->transport_number = $transport_number;

        return $this;
    }

    public function getClientNumber(): ?string
    {
        return $this->client_number;
    }

    public function setClientNumber(string $client_number): self
    {
        $this->client_number = $client_number;

        return $this;
    }

    public function getReviewType(): ?string
    {
        return $this->review_type;
    }

    public function setReviewType(string $review_type): self
    {
        $this->review_type = $review_type;

        return $this;
    }

    public function getPaymentType(): ?PaymentType
    {
        return $this->PaymentType;
    }

    public function setPaymentType(?PaymentType $PaymentType): self
    {
        $this->PaymentType = $PaymentType;

        return $this;
    }

    public function getTransportation(): ?Transportation
    {
        return $this->Transportation;
    }

    public function setTransportation(?Transportation $Transportation): self
    {
        $this->Transportation = $Transportation;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->Client;
    }

    public function setClient(?Client $Client): self
    {
        $this->Client = $Client;

        return $this;
    }

    /**
     * @return Collection|PurchaseProductVariant[]
     */
    public function getProductVariants(): Collection
    {
        return $this->ProductVariants;
    }

    public function addProductVariant(PurchaseProductVariant $purchaseProductVariant): self
    {
        if (!$this->ProductVariants->contains($purchaseProductVariant)) {
            $this->ProductVariants[] = $purchaseProductVariant;
            $purchaseProductVariant->setPurchase($this);
        }

        return $this;
    }

    public function removeProductVariant(PurchaseProductVariant $productVariant): self
    {
        if ($this->ProductVariants->removeElement($productVariant)) {
            // set the owning side to null (unless already changed)
            if ($productVariant->getPurchase() === $this) {
                $productVariant->setPurchase(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PurchaseEvent>
     */
    public function getPurchaseEvents(): Collection
    {
        return $this->purchaseEvents;
    }

    public function addPurchaseEvent(PurchaseEvent $purchaseEvent): self
    {
        if (!$this->purchaseEvents->contains($purchaseEvent)) {
            $this->purchaseEvents->add($purchaseEvent);
            $purchaseEvent->setPurchase($this);
        }

        return $this;
    }

    public function removePurchaseEvent(PurchaseEvent $purchaseEvent): self
    {
        if ($this->purchaseEvents->removeElement($purchaseEvent)) {
            // set the owning side to null (unless already changed)
            if ($purchaseEvent->getPurchase() === $this) {
                $purchaseEvent->setPurchase(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Note>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(Note $note): self
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setPurchase($this);
        }

        return $this;
    }

    public function removeNote(Note $note): self
    {
        if ($this->notes->removeElement($note)) {
            // set the owning side to null (unless already changed)
            if ($note->getPurchase() === $this) {
                $note->setPurchase(null);
            }
        }

        return $this;
    }

    public function setPurchasePrice($price): self
    {
        $this->purchasePrice = $price;

        return $this;
    }

    public function getPurchasePrice(): ?float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePriceVat($price): self
    {
        $this->purchasePriceVat = $price;

        return $this;
    }

    public function getPurchasePriceVat(): ?float
    {
        return $this->purchasePriceVat;
    }

    public function getClientDiscount(): ?ClientDiscount
    {
        return $this->clientDiscount;
    }

    public function setClientDiscount(?ClientDiscount $clientDiscount): static
    {
        $this->clientDiscount = $clientDiscount;

        return $this;
    }

    public function getClientAddress(): ?ClientAddress
    {
        return $this->clientAddress;
    }

    public function setClientAddress(?ClientAddress $clientAddress): static
    {
        $this->clientAddress = $clientAddress;

        return $this;
    }

}
