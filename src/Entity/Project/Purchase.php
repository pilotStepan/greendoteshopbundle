<?php

namespace Greendot\EshopBundle\Entity\Project;

use JetBrains\PhpStorm\ArrayShape;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\ApiResource\PurchaseSession;
use Greendot\EshopBundle\Dto\PurchaseCheckoutInput;
use Greendot\EshopBundle\Entity\PurchaseTracking;
use Greendot\EshopBundle\StateProcessor\CartStateProcessor;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\StateProcessor\PurchaseCheckoutProcessor;
use Greendot\EshopBundle\StateProvider\PurchaseStateProvider;
use Greendot\EshopBundle\StateProvider\PurchaseWishlistStateProvider;
use Greendot\EshopBundle\Validator\Constraints\ClientDiscountAvailability;
use Greendot\EshopBundle\Validator\Constraints\TransportationPaymentAvailability;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: '`purchase`')]
#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new GetCollection(
            uriTemplate: '/purchases/session',
            provider: PurchaseStateProvider::class,
        ),
        new Get(
            uriTemplate: '/purchases/session',
            provider: PurchaseStateProvider::class,
        ),
        new Get(
            uriTemplate: '/purchases/wishlist',
            normalizationContext: ['groups' => ['purchase:wishlist']],
            denormalizationContext: ['groups' => ['default']],
            provider: PurchaseWishlistStateProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/purchases/wishlist',
            normalizationContext: ['groups' => ['purchase:wishlist']],
            denormalizationContext: ['groups' => ['default']],
            provider: PurchaseWishlistStateProvider::class,
        ),
        new Post(),
        new Post(
            uriTemplate: '/purchases/session/checkout',
            status: 200,
            denormalizationContext: [
                'groups' => ['purchase:checkout'],
            ],
            input: PurchaseCheckoutInput::class,
            processor: PurchaseCheckoutProcessor::class,
        ),
        new Patch(
            uriTemplate: '/purchases/session',
            denormalizationContext: ['groups' => ['purchase:write']],
            read: true,
            provider: PurchaseStateProvider::class,
            processor: CartStateProcessor::class,
        ),
        new Patch(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['purchase:read']],
    denormalizationContext: ['groups' => ['purchase:write']],
    paginationEnabled: false
)]
#[Get(provider: PurchaseStateProvider::class)]
#[ApiFilter(PurchaseSession::class)]
#[TransportationPaymentAvailability]
class Purchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
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

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $date_delivery;

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
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $internalNumber;

    #[ORM\ManyToOne(targetEntity: PaymentType::class, cascade: ['persist'], inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $PaymentType;

    #[ORM\ManyToOne(targetEntity: Transportation::class, cascade: ['persist'], inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['purchase:read', 'purchase:write'])]
    private $Transportation;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
    private $client;

    #[Groups(['purchase:read', 'purchase:write'])]
    private $total_price_no_services;

    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
    private $total_price;

    /**
     * @return array{
     *     total_with_vat_main: float,
     *     total_no_vat_main: float,
     *     total_with_vat_secondary: float,
     *     total_no_vat_secondary: float
     * }
     */
    #[Groups(['purchase:wishlist'])]
    private array $prices;

    #[Groups(['purchase:read', 'purchase:write'])]
    private $transportation_price;

    #[Groups(['purchase:read', 'purchase:write'])]
    private $payment_price;

    #[ORM\OneToMany(targetEntity: PurchaseProductVariant::class, mappedBy: 'purchase', cascade: ['persist', 'remove'])]
    #[Groups(['purchase:read', 'purchase:write', 'purchase:wishlist'])]
    private Collection $ProductVariants;

    #[ORM\OneToMany(targetEntity: PurchaseEvent::class, mappedBy: 'purchase', cascade: ['persist', 'remove'])]
    private Collection $purchaseEvents;

    #[ORM\OneToMany(targetEntity: PurchaseTracking::class, mappedBy: 'purchase', cascade: ['persist', 'remove'])]
    private Collection $purchaseTrackings;

    /* Vouchers bought in the order (assigned via PATCH on Purchase entity) */
    #[ORM\OneToMany(targetEntity: Voucher::class, mappedBy: 'Purchase_issued', cascade: ['persist', 'remove'])]
    private Collection $VouchersIssued;

    /* Vouchers used to pay the order (assigned via PATCH on Voucher entity) */
    #[ORM\OneToMany(targetEntity: Voucher::class, mappedBy: 'purchaseUsed', cascade: ['persist', 'remove'])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private Collection $vouchersUsed;

    #[ORM\ManyToOne(targetEntity: ClientDiscount::class, inversedBy: 'purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[ClientDiscountAvailability]
    #[Groups(['purchase:read', 'purchase:write'])]
    private ?ClientDiscount $clientDiscount;

    #[ORM\ManyToMany(targetEntity: Consent::class, mappedBy: 'purchases')]
    private Collection $Consents;

    #[ORM\OneToOne(targetEntity: PurchaseAddress::class, inversedBy: 'purchase')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private ?PurchaseAddress $purchaseAddress = null;

    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'purchase', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payments;

    #[ORM\OneToMany(targetEntity: PurchaseDiscussion::class, mappedBy: 'purchase')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['purchase:read', 'purchase:write', 'event_purchase'])]
    private Collection $purchaseDiscussions;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['purchase:read', 'purchase:write'])]
    private bool $isDiscussionResolved = false;

    #[ORM\ManyToOne(targetEntity: Branch::class, inversedBy: 'Purchases')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['purchase:read', 'purchase:write'])]
    private ?Branch $branch = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['purchase:read'])]
    private bool $isVatExempted = false;
    
    #[ORM\OneToMany(mappedBy: 'purchase', targetEntity: TransportationEvent::class, cascade: ['persist', 'remove'])]
    private Collection $transportationEvents;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $affiliateId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $adId = null;

    public function __construct()
    {
        $this->date_issue = new \DateTime();
        $this->ProductVariants = new ArrayCollection();
        $this->purchaseEvents = new ArrayCollection();
        $this->purchaseTrackings = new ArrayCollection();
        $this->VouchersIssued = new ArrayCollection();
        $this->vouchersUsed = new ArrayCollection();
        $this->Consents = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->purchaseDiscussions = new ArrayCollection();
        $this->transportationEvents = new ArrayCollection();
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

    public function getDateDelivery(): ?\DateTimeInterface
    {
        return $this->date_delivery;
    }

    public function setDateDelivery(?\DateTimeInterface $date_delivery): self
    {
        $this->date_delivery = $date_delivery;

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

    public function setTransportNumber(?string $transport_number): self
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
        // If transportation is being removed, also remove dependent payment type
        if ($Transportation === null && $this->Transportation !== null) {
            $this->PaymentType = null;
        }
        $this->Transportation = $Transportation;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $Client): self
    {
        $this->client = $Client;

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

    public function getInternalNumber(): ?string
    {
        return $this->internalNumber;
    }

    public function setInternalNumber($internalNumber): static
    {
        $this->internalNumber = $internalNumber;
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
        return $this->vouchersUsed;
    }

    public function addVoucherUsed(Voucher $voucher): static
    {
        if (!$this->vouchersUsed->contains($voucher)) {
            $this->vouchersUsed->add($voucher);
            $voucher->setPurchaseUsed($this);
        }

        return $this;
    }

    public function removeVoucherUsed(Voucher $voucher): static
    {
        if ($this->vouchersUsed->removeElement($voucher)) {
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

    public function getCheckedRequiredConsents(): Collection
    {
        return $this->Consents->filter(fn(Consent $consent) => $consent->isIsRequired());
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

    /**
     * @return float
     */
    public function getTransportationPrice()
    {
        return $this->transportation_price;
    }

    /**
     * @param float $transportation_price
     */
    public function setTransportationPrice($transportation_price): void
    {
        $this->transportation_price = $transportation_price;
    }

    /**
     * @return float
     */
    public function getPaymentPrice()
    {
        return $this->payment_price;
    }

    /**
     * @param float $payment_price
     */
    public function setPaymentPrice($payment_price): void
    {
        $this->payment_price = $payment_price;
    }

    public function getPurchaseAddress(): ?PurchaseAddress
    {
        return $this->purchaseAddress;
    }

    public function setPurchaseAddress(?PurchaseAddress $purchaseAddress): static
    {
        $this->purchaseAddress = $purchaseAddress;

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

    /**
     * @return float
     */
    public function getTotalPriceNoServices()
    {
        return $this->total_price_no_services;
    }

    /**
     * @param float $total_price_no_services
     */
    public function setTotalPriceNoServices($total_price_no_services): void
    {
        $this->total_price_no_services = $total_price_no_services;
    }

    /**
     * @return Collection<int, PurchaseDiscussion>
     */
    public function getPurchaseDiscussions(): Collection
    {
        return $this->purchaseDiscussions;
    }

    public function addDiscussion(PurchaseDiscussion $discussion): static
    {
        if (!$this->purchaseDiscussions->contains($discussion)) {
            $this->purchaseDiscussions->add($discussion);
            $discussion->setPurchase($this);
        }

        return $this;
    }

    public function removeDiscussion(PurchaseDiscussion $discussion): static
    {
        if ($this->purchaseDiscussions->removeElement($discussion)) {
            // set the owning side to null (unless already changed)
            if ($discussion->getPurchase() === $this) {
                $discussion->setPurchase(null);
            }
        }

        return $this;
    }

    public function getLastAdminMessage(): ?PurchaseDiscussion
    {
        $clientMessages = $this->purchaseDiscussions->filter(
            fn(PurchaseDiscussion $discussion) => $discussion->getIsAdmin(),
        );

        if ($clientMessages->isEmpty()) {
            return null;
        }

        $iterator = $clientMessages->getIterator();
        $iterator->uasort(function (PurchaseDiscussion $a, PurchaseDiscussion $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        return $iterator->count() > 0 ? $iterator->current() : null;
    }

    public function isDiscussionResolved(): bool
    {
        return $this->isDiscussionResolved;
    }

    public function setIsDiscussionResolved(bool $isDiscussionResolved): void
    {
        $this->isDiscussionResolved = $isDiscussionResolved;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    public function isVatExempted(): bool
    {
        return $this->isVatExempted;
    }

    public function getIsVatExempted(): bool
    {
        return $this->isVatExempted;
    }


    public function setIsVatExempted(bool $isVatExempted): static
    {
        $this->isVatExempted = $isVatExempted;
        return $this;
    }

     
    public function getTransportationEvents(): Collection
    {
        return $this->transportationEvents;
    }

    public function addTransportationEvent(TransportationEvent $event): static
    {
        if (!$this->transportationEvents->contains($event)) {
            $this->transportationEvents->add($event);
            $event->setPurchase($this);
        }

        return $this;
    }

    public function removeTransportationEvent(TransportationEvent $event): static
    {
        if ($this->transportationEvents->removeElement($event)) {
            if ($event->getPurchase() === $this) {
                $event->setPurchase(null);
            }
        }

        return $this;
    }

    public function getLatestTransportationEvent(): ?TransportationEvent
    {
        if ($this->transportationEvents->isEmpty()) {
            return null;
        }

        $events = $this->transportationEvents->toArray();
        usort($events, function ($a, $b) {
            return $b->getOccurredAt() <=> $a->getOccurredAt();
        });

        return $events[0] ?? null;
    }

    public function setAffiliateId(?string $affiliateId) : self
    {
        $this->affiliateId = $affiliateId;

        return $this;
    }

    public function getAffiliateId() : ?string
    {
        return $this->affiliateId;
    }

    public function setAdId(?int $adId) : self
    {
        $this->adId = $adId;

        return $this;
    }

    public function getAdId() : ?int
    {
        return $this->adId;
    }

    public function getPrices(): array
    {
        return $this->prices;
    }

    public function setPrices(array $prices): Purchase
    {
        $this->prices = $prices;
        return $this;
    }
}
