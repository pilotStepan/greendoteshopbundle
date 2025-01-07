<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\ApiResource\PurchaseSession;
use Greendot\EshopBundle\Entity\Project\Branch;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Entity\Project\Note;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\PurchaseEvent;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\PurchaseTracking;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\StateProvider\PurchaseStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: '`purchase`')]
#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new GetCollection(
            uriTemplate: '/purchases/session',
            provider: PurchaseStateProvider::class,
        ),
        new Get(),
        new Post(),
        new Patch(
            uriTemplate: '/purchases/session',
            //processor: SessionPurchaseStateProcessor::class,
            provider: PurchaseStateProvider::class,
            denormalizationContext: ['groups' => ['purchase:write']],
            read: true,
        ),
        new Put(),
        new Delete(),
        new Patch(),
    ],
    normalizationContext: ['groups' => ['purchase:read']],
    denormalizationContext: ['groups' => ['purchase:write']],
    paginationEnabled: false
)]
#[Get(provider: PurchaseStateProvider::class)]
//#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact'])]
#[ApiFilter(PurchaseSession::class)]
class Purchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $id;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $date_issue;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $date_expedition;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $date_invoiced;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $state = "draft";

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $invoice_number;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $is_exported_stock;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $is_exported_book;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $is_exported_transport;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $transport_number;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $client_number;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $review_type;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $name;

    #[ORM\ManyToOne(targetEntity: PaymentType::class, cascade: ['persist'], inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $PaymentType;

    #[ORM\ManyToOne(targetEntity: Transportation::class, cascade: ['persist'], inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $Transportation;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $Client;

    #[Groups(['purchase:read', 'purchase:write'])]
    private $total_price;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseProductVariant::class, cascade: ['persist', 'remove'])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private Collection $ProductVariants;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseEvent::class, cascade: ['persist', 'remove'])]
    private Collection $purchaseEvents;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: Note::class, cascade: ['persist', 'remove'])]
    private Collection $notes;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: PurchaseTracking::class, cascade: ['persist', 'remove'])]
    private Collection $purchaseTrackings;

    #[ORM\OneToMany(mappedBy: 'Purchase_issued', targetEntity: Voucher::class, cascade: ['persist', 'remove'])]
    private Collection $VouchersIssued;

    #[ORM\OneToMany(mappedBy: 'Purchase_used', targetEntity: Voucher::class, cascade: ['persist', 'remove'])]
    #[Groups(['purchase:read'])]
    private Collection $VouchersUsed;

    #[ORM\ManyToMany(targetEntity: Consent::class, mappedBy: 'Purchases')]
    private Collection $Consents;

    #[ORM\ManyToOne(inversedBy: 'Purchase')]
    #[Groups(['purchase:read'])]
    private ?ClientAddress $clientAddress = null;

    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: Payment::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $payments;

    #[ORM\ManyToOne(inversedBy: 'purchase')]
    #[Groups(['purchase:read'])]
    private ?ClientDiscount $clientDiscount;

    #[ORM\ManyToOne(targetEntity: Branch::class, inversedBy: 'Purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private ?Branch $branch = null;

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    public function __construct()
    {
        $this->date_issue = new \DateTime();
        $this->ProductVariants = new ArrayCollection();
        $this->purchaseEvents = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->purchaseTrackings = new ArrayCollection();
        $this->VouchersIssued = new ArrayCollection();
        $this->VouchersUsed = new ArrayCollection();
        $this->Consents = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getProducts(): Collection
    {
        $products = new ArrayCollection();

        foreach ($this->getProductVariants() as $purchaseProductVariant) {
            $productVariant = $purchaseProductVariant->getProductVariant();
            if ($productVariant) {
                $product = $productVariant->getProduct();
                if ($product && !$products->contains($product)) {
                    $products->add($product);
                }
            }
        }

        return $products;
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


    public function getName()
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
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

    /**
     * @return Collection<int, PurchaseTracking>
     */
    public function getPurchaseTrackings(): Collection
    {
        return $this->purchaseTrackings;
    }

    public function addPurchaseTracking(PurchaseTracking $purchaseTracking): static
    {
        if (!$this->purchaseTrackings->contains($purchaseTracking)) {
            $this->purchaseTrackings->add($purchaseTracking);
            $purchaseTracking->setPurchase($this);
        }

        return $this;
    }

    public function removePurchaseTracking(PurchaseTracking $purchaseTracking): static
    {
        if ($this->purchaseTrackings->removeElement($purchaseTracking)) {
            // set the owning side to null (unless already changed)
            if ($purchaseTracking->getPurchase() === $this) {
                $purchaseTracking->setPurchase(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Voucher>
     */
    public function getVouchersIssued(): Collection
    {
        return $this->VouchersIssued;
    }

    public function addVoucherIssued(Voucher $voucher): static
    {
        if (!$this->VouchersIssued->contains($voucher)) {
            $this->VouchersIssued->add($voucher);
            $voucher->setPurchaseIssued($this);
        }

        return $this;
    }

    public function removeVoucherIssued(Voucher $voucher): static
    {
        if ($this->VouchersIssued->removeElement($voucher)) {
            // set the owning side to null (unless already changed)
            if ($voucher->getPurchaseIssued() === $this) {
                $voucher->setPurchaseIssued(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, Voucher>
     */
    public function getVouchersUsed(): Collection
    {
        return $this->VouchersUsed;
    }

    public function addVoucherUsed(Voucher $voucher): static
    {
        if (!$this->VouchersUsed->contains($voucher)) {
            $this->VouchersUsed->add($voucher);
            $voucher->setPurchaseUsed($this);
        }

        return $this;
    }

    public function removeVoucherUsed(Voucher $voucher): static
    {
        if ($this->VouchersUsed->removeElement($voucher)) {
            // set the owning side to null (unless already changed)
            if ($voucher->getPurchaseUsed() === $this) {
                $voucher->setPurchaseUsed(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Consent>
     */
    public function getConsents(): Collection
    {
        return $this->Consents;
    }

    public function addConsent(Consent $consent): static
    {
        if (!$this->Consents->contains($consent)) {
            $this->Consents->add($consent);
            $consent->addPurchase($this);
        }

        return $this;
    }

    public function removeConsent(Consent $consent): static
    {
        if ($this->Consents->removeElement($consent)) {
            $consent->removePurchase($this);
        }

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

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setPurchase($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getPurchase() === $this) {
                $payment->setPurchase(null);
            }
        }

        return $this;
    }

    /**
     * @return float
     */
    public function getTotalPrice()
    {
        return $this->total_price;
    }

    /**
     * @param float $total_price
     */
    public function setTotalPrice($total_price): void
    {
        $this->total_price = $total_price;
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

}
