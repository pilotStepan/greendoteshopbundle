<?php

namespace Greendot\EshopBundle\Entity\Project;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogType;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogState;
use Symfony\Component\Validator\Constraints as Assert;
use Greendot\EshopBundle\Repository\Project\WatchdogRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Karser\Recaptcha3Bundle\Validator\Constraints as RecaptchaAssert;


#[ORM\Entity(repositoryClass: WatchdogRepository::class)]
#[ORM\Table(name: 'watchdog')]
#[ORM\Index(name: 'watchdog_variant_lookup', columns: ['type', 'state', 'product_variant_id'])]
#[ORM\Index(name: 'watchdog_email_idx', columns: ['email'])]
#[UniqueEntity(
    fields: ['type', 'productVariant', 'email'],
    message: 'Na tento produkt už upozornění pro tento e-mail existuje.',
    repositoryMethod: 'isActiveUnique',
    errorPath: 'email',
    groups: ['watchdog:subscribe'],
)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/watchdogs/subscribe',
            status: 204,
            denormalizationContext: ['groups' => ['watchdog:subscribe']],
            validationContext: ['groups' => ['watchdog:subscribe']],
            output: false,
            read: false,
        ),
    ]
)]
class Watchdog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(nullable: false, enumType: WatchdogType::class)]
    #[Groups(['watchdog:subscribe'])]
    private WatchdogType $type = WatchdogType::VariantAvailable;

    #[ORM\Column(nullable: false, enumType: WatchdogState::class)]
    private WatchdogState $state = WatchdogState::Active;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['watchdog:subscribe'])]
    #[Assert\NotNull(groups: ['watchdog:subscribe'])]
    private ?ProductVariant $productVariant;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    #[Groups(['watchdog:subscribe'])]
    #[Assert\NotBlank(groups: ['watchdog:subscribe'])]
    #[Assert\Email(groups: ['watchdog:subscribe'])]
    private ?string $email;

    /* API-only attribute */
    #[ApiProperty(writable: true)]
    #[Groups(['watchdog:subscribe'])]
    #[Assert\NotBlank(groups: ['watchdog:subscribe'])]
    #[RecaptchaAssert\Recaptcha3(groups: ['watchdog:subscribe'])]
    private string $captcha = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $queuedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    public function __construct()
    {
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

    public function setType(WatchdogType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getState(): WatchdogState
    {
        return $this->state;
    }

    public function setState(WatchdogState $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function setProductVariant(?ProductVariant $productVariant): Watchdog
    {
        $this->productVariant = $productVariant;
        return $this;
    }

    public function setEmail(string $email): Watchdog
    {
        $this->email = mb_strtolower(trim($email));
        return $this;
    }

    public function getCaptcha(): string
    {
        return $this->captcha;
    }

    public function setCaptcha(string $captcha): self
    {
        $this->captcha = $captcha;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->state === WatchdogState::Completed || $this->completedAt !== null;
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getQueuedAt(): ?DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function markQueued(?DateTimeImmutable $when = null): self
    {
        $this->queuedAt = $this->queuedAt ?? ($when ?? new DateTimeImmutable());

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markCompleted(?DateTimeImmutable $when = null): self
    {
        $this->state = WatchdogState::Completed;
        $this->completedAt = $when ?? new DateTimeImmutable();

        return $this;
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
