<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['client:read']],
    denormalizationContext: ['groups' => ['client:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'surname' => 'partial', 'mail' => 'partial', 'phone' => 'partial', 'city' => 'partial', 'ic' => 'partial'])]
class Client implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $id;


    #[ORM\Column(type: 'string', length: 65, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $name;

    #[ORM\Column(type: 'string', length: 65, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $surname;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $title;

    #[ORM\Column(type: 'string', length: 55, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $phone;

    #[ORM\Column(type: 'string', length: 55, nullable: true)]
    #[Groups(['client:read', 'client:write', 'order:read', 'order:write', 'purchase:read', 'purchase:write'])]
    private $mail;

    #[ORM\OneToMany(mappedBy: 'Client', targetEntity: Purchase::class)]
    #[Groups(['client:read', 'client:write'])]
    private $orders;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $password;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Comment::class)]
    #[Groups(['client:read', 'client:write'])]
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
    private Collection $clientAddresses;


    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->clientDiscounts = new ArrayCollection();
        $this->clientAddresses = new ArrayCollection();

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
            // set the owning side to null (unless already changed)
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

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUserIdentifier(): string
    {
        return $this->getMail();
    }

    public function getPassword(): ?string
    {
        return $this->password;
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
            // set the owning side to null (unless already changed)
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

    public function getFullname(){
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
            // set the owning side to null (unless already changed)
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

}
