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
use Doctrine\DBAL\Types\Types;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\StateProcessor\ClientRegistrationStateProcessor;
use Greendot\EshopBundle\Validator\Constraints\ClientMailUnique;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Greendot\EshopBundle\StateProvider\ClientStateProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER") and object.owner == user'),
        new GetCollection(uriTemplate: '/clients/session', provider: ClientStateProvider::class),
        new Post(validationContext: ['groups' => ['Default', 'client:create']], processor: ClientRegistrationStateProcessor::class),
        new Get(security: 'is_granted("ROLE_USER") and object.owner == user'),
        new Put(security: 'is_granted("ROLE_USER") and object.owner == user', processor: ClientRegistrationStateProcessor::class),
        new Patch(
            denormalizationContext: [
                'groups' => ['client:write'],
                'api_allow_update' => true,
            ],
            security: 'is_granted("ROLE_USER") and object.owner == user',
            processor: ClientRegistrationStateProcessor::class
        ),
        new Delete(security: 'is_granted("ROLE_USER") and object.owner == user'),
    ],
    normalizationContext: ['groups' => ['client:read']],
    denormalizationContext: ['groups' => ['client:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'surname' => 'partial', 'mail' => 'partial', 'phone' => 'partial'])]
class Client implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['client:read', 'order:read', 'purchase:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 65, nullable: true)]
    #[Assert\NotBlank]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $name;

    #[ORM\Column(type: 'string', length: 65, nullable: true)]
    #[Assert\NotBlank]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $surname;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $title;

    #[ORM\Column(type: 'string', length: 55, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $phone;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[ClientMailUnique(groups: ['client:create'])]
    #[ORM\Column(type: 'string', length: 55, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $mail;

    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'client')]
    #[Groups(['client:read'])]
    private $orders;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $password;

    #[Assert\NotBlank(groups: ['client:create'])]
    #[Groups(['client:write'])]
    #[SerializedName('password')]
    private ?string $plainPassword = null;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'client')]
    #[Groups(['client:read'])]
    private Collection $comments;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?bool $is_anonymous = null;

    #[ORM\OneToMany(targetEntity: ClientDiscount::class, mappedBy: 'client')]
    private Collection $clientDiscounts;

    #[ORM\Column(nullable: true, options: ['default' => 0])]
    #[Groups(['client:read', 'client:write'])]
    private ?bool $agree_newsletter = null;

    #[ORM\OneToMany(targetEntity: ClientAddress::class, mappedBy: 'client', cascade: ['persist'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['client:read', 'client:write'])]
    private Collection $clientAddresses;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $description = null;

    #[ORM\OneToMany(targetEntity: Purchase::class, mappedBy: 'client')]
    #[Groups(['clientAddress:read', 'clientAddress:write'])]
    private Collection $purchases;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->clientDiscounts = new ArrayCollection();
        $this->clientAddresses = new ArrayCollection();
        $this->purchases = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(?string $surname): self
    {
        $this->surname = $surname;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getMail(): ?string
    {
        return $this->mail;
    }

    public function setMail(?string $mail): self
    {
        $this->mail = $mail;

        return $this;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection[Purchase]
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Purchase $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setClient($this);
        }

        return $this;
    }

    public function removeOrder(Purchase $order): self
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getClient() === $this) {
                $order->setClient(null);
            }
        }

        return $this;
    }

    public function getRoles(): array
    {
        if ($this->isIsAnonymous()) {
            return ['ROLE_ANONYMOUS_USER'];
        }
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->getId();
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setClient($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getClient() === $this) {
                $comment->setClient(null);
            }
        }

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getFullname(): string
    {
        return $this->name . " " . $this->surname;
    }

    public function isIsAnonymous(): ?bool
    {
        return $this->is_anonymous;
    }

    public function setIsAnonymous(?bool $is_anonymous): static
    {
        $this->is_anonymous = $is_anonymous;

        return $this;
    }

    /**
     * @return Collection<int, ClientDiscount>
     */
    public function getClientDiscounts(): Collection
    {
        return $this->clientDiscounts;
    }

    public function addClientDiscount(ClientDiscount $clientDiscount): self
    {
        if (!$this->clientDiscounts->contains($clientDiscount)) {
            $this->clientDiscounts->add($clientDiscount);
            $clientDiscount->setClient($this);
        }

        return $this;
    }

    public function removeClientDiscount(ClientDiscount $clientDiscount): self
    {
        if ($this->clientDiscounts->removeElement($clientDiscount)) {
            if ($clientDiscount->getClient() === $this) {
                $clientDiscount->setClient(null);
            }
        }

        return $this;
    }

    public function isAgreeNewsletter(): ?bool
    {
        return $this->agree_newsletter;
    }

    public function setAgreeNewsletter(?bool $agreeNewsletter): self
    {
        $this->agree_newsletter = $agreeNewsletter;

        return $this;
    }

    /**
     * @return Collection<int, ClientAddress>
     */
    public function getClientAddresses(): Collection
    {
        return $this->clientAddresses;
    }

    public function getPrimaryAddress(): ?ClientAddress
    {
        return $this->clientAddresses
            ->filter(fn($address) => $address->getIsPrimary())
            ->first() ?: null;
    }

    public function addClientAddress(ClientAddress $clientAddress): static
    {
        if (!$this->clientAddresses->contains($clientAddress)) {
            $this->clientAddresses->add($clientAddress);
            $clientAddress->setClient($this);

            if ($this->clientAddresses->count() === 1) {
                $clientAddress->setIsPrimary(true);
            }
        }

        return $this;
    }

    public function removeClientAddress(ClientAddress $clientAddress): static
    {
        if ($this->clientAddresses->removeElement($clientAddress)) {
            // set the owning side to null (unless already changed)
            if ($clientAddress->getClient() === $this) {
                $clientAddress->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Purchase>
     */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases->add($purchase);
            $purchase->setClient($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getClient() === $this) {
                $purchase->setClient(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }
}
