<?php

namespace Greendot\EshopBundle\Entity\Project;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Dto\WatchdogSubscribeDto;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogType;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogState;
use Greendot\EshopBundle\Repository\Project\WatchdogRepository;


#[ORM\Entity(repositoryClass: WatchdogRepository::class)]
#[ORM\Table(name: 'watchdog')]
#[ORM\Index(name: 'watchdog_variant_lookup', columns: ['type', 'state', 'product_variant_id'])]
#[ORM\Index(name: 'watchdog_email_idx', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'watchdog_dedupe_uniq', columns: ['type', 'product_variant_id', 'email'])]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/watchdogs/subscribe',
            status: 204,
            input: WatchdogSubscribeDto::class,
        )
    ]
)]
class Watchdog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(enumType: WatchdogType::class)]
    private WatchdogType $type = WatchdogType::VariantAvailable;

    #[ORM\Column(enumType: WatchdogState::class)]
    private WatchdogState $state = WatchdogState::Active;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProductVariant $productVariant;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    /**
     * Idempotency key for the last queued event.
     *
     * This prevents dispatching the same async notification repeatedly when IS calls
     * /notify-variant-available multiple times.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastQueuedEventKey = null;

    /**
     * Idempotency key for the last successfully SENT event.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastSentEventKey = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastQueuedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastNotifiedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $attemptCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    public function __construct(ProductVariant $productVariant, string $email)
    {
        $this->productVariant = $productVariant;
        $this->email = mb_strtolower(trim($email));
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): WatchdogType
    {
        return $this->type;
    }

    public function getState(): WatchdogState
    {
        return $this->state;
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getLastQueuedEventKey(): ?string
    {
        return $this->lastQueuedEventKey;
    }

    public function getLastSentEventKey(): ?string
    {
        return $this->lastSentEventKey;
    }

    public function shouldQueueEvent(string $eventKey): bool
    {
        return $this->lastQueuedEventKey !== $eventKey;
    }

    public function markQueued(string $eventKey, ?DateTimeImmutable $when = null): self
    {
        $this->lastQueuedEventKey = $eventKey;
        $this->lastQueuedAt = $when ?? new DateTimeImmutable();

        return $this;
    }

    public function markSent(string $eventKey, ?DateTimeImmutable $when = null): self
    {
        $this->attemptCount++;
        $this->lastSentEventKey = $eventKey;
        $this->lastNotifiedAt = $when ?? new DateTimeImmutable();

        $this->state = WatchdogState::Completed;
        $this->completedAt = $this->lastNotifiedAt;
        $this->lastError = null;

        return $this;
    }

    public function markFailed(string $eventKey, string $error): self
    {
        $this->attemptCount++;
        $this->lastError = $error;
        $this->lastQueuedEventKey = $eventKey;

        return $this;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** @return array<string, mixed>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /** @param array<string, mixed>|null $meta */
    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }
}
