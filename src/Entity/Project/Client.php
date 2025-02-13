<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
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

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: 'is_granted("ROLE_USER") and object.id == user.id'),
        new GetCollection(
            uriTemplate: '/clients/session',
            provider: ClientStateProvider::class
        ),
        new Post(processor: ClientRegistrationStateProcessor::class, validationContext: ['groups' => ['Default', 'client:create']]),
        new Get(security: 'is_granted("ROLE_USER") and object.id == user.id'),
        new Put(processor: ClientRegistrationStateProcessor::class,
            security: 'is_granted("ROLE_USER") and object.id == user.id'),
        new Patch(processor: ClientRegistrationStateProcessor::class,
            security: 'is_granted("ROLE_USER") and object.id == user.id'),
        new Delete(security: 'is_granted("ROLE_USER") and object.id == user.id'),
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
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
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

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Purchase::class)]
    #[Groups(['client:read'])]
    private $orders;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $password;

    #[Assert\NotBlank(groups: ['client:create'])]
    #[Groups(['client:write'])]
    private ?string $plainPassword = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Comment::class)]
    #[Groups(['client:read'])]
    private Collection $comments;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(nullable: true)]
    private ?bool $isAnonymous = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ClientDiscount::class)]
    private Collection $clientDiscounts;

    #[ORM\Column(nullable: true, options: ['default' => 0])]
    private ?bool $agreeNewsletter = null;

    #[ORM\OneToMany(mappedBy: 'Client', targetEntity: ClientAddress::class)]
    #[Groups(['client:read', 'client:write'])]
    private Collection $clientAddresses;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Purchase::class)]
    #[Groups(['clientAddress:read', 'clientAddress:write'])]
    private Collection $purchases;

    public function __construct()
    {
        $this->orders          = new ArrayCollection();
        $this->comments        = new ArrayCollection();
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
     * @return Collection|Purchase[]
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
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->getMail();
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

    public function getFullname()
    {
        return $this->name . " " . $this->surname;
    }

    public function isIsAnonymous(): ?bool
    {
        return $this->isAnonymous;
    }

    public function setIsAnonymous(?bool $isAnonymous): self
    {
        $this->isAnonymous = $isAnonymous;

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
        return $this->agreeNewsletter;
    }

    public function setAgreeNewsletter(?bool $agreeNewsletter): self
    {
        $this->agreeNewsletter = $agreeNewsletter;

        return $this;
    }


    /**
     * @return Collection<int, ClientAddress>
     */
    public function getClientAddresses(): Collection
    {
        return $this->clientAddresses;
    }

    public function addClientAddress(ClientAddress $clientAddress): static
    {
        if (!$this->clientAddresses->contains($clientAddress)) {
            $this->clientAddresses->add($clientAddress);
            $clientAddress->setClient($this);
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
            $purchase->setClientAddress($this);
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase)) {
            // set the owning side to null (unless already changed)
            if ($purchase->getClientAddress() === $this) {
                $purchase->setClientAddress(null);
            }
        }

        return $this;
    }
}
