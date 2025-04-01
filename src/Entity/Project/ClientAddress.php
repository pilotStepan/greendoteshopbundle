<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Concrete address entity for client information, handling billing/shipping addresses with primary address management
 */
#[ORM\Entity]
#[ApiResource(
    operations: [
        new Patch()
    ],
    normalizationContext: [
        'groups' => ['client_address:read'],
        'order' => ['is_primary' => 'DESC'],
    ],
    denormalizationContext: [
        'groups' => ['client_address:write'],
        'allow_extra_attributes' => true,
    ]
)]
class ClientAddress extends Address
{
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'clientAddresses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private ?Client $client = null;

    #[ORM\Column(nullable: false, options: ['default' => false])]
    #[Groups(['client:read', 'client:write', 'client_address:write'])]
    private ?bool $is_primary = false;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'client_address:write'])]
    private ?string $name = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getIsPrimary(): ?bool
    {
        return $this->is_primary;
    }

    // Ensures exactly one primary address exists per client while handling all edge cases
    public function setIsPrimary(?bool $isPrimary): static
    {
        $client = $this->getClient();

        if (!$isPrimary) {
            if ($client && $client->getClientAddresses()->count() === 1) {
                $this->is_primary = true;
                return $this;
            }

            $this->is_primary = false;
            return $this;
        }

        if (!$client) {
            $this->is_primary = false;
            return $this;
        }

        if ($this->is_primary === true) {
            return $this;
        }

        if ($currentPrimary = $client->getPrimaryAddress()) {
            if ($currentPrimary === $this) {
                return $this;
            }
            $currentPrimary->is_primary = false;
        }

        $this->is_primary = true;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function autoSetPrimary(): void
    {
        if ($this->client && $this->client->getClientAddresses()->count() === 1 &&
            !$this->client->getClientAddresses()->exists(fn($k, $a) => $a->getIsPrimary())
        ) {
            $this->is_primary = true;
        }
    }
}
