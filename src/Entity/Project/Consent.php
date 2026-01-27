<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use Greendot\EshopBundle\Repository\Project\ConsentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\Entity\Interface\SoftDeletedInterface;
use Greendot\EshopBundle\Entity\Trait\SoftDeletedTrait;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['consent:read']],
)]
#[ORM\Entity(repositoryClass: ConsentRepository::class)]
class Consent implements Translatable, SoftDeletedInterface
{
    use SoftDeletedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consent:read', 'purchase:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['consent:read'])]
    #[Gedmo\Translatable]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['consent:read'])]
    #[Gedmo\Translatable]
    private ?string $description = null;

    #[ORM\Column] 
    #[Groups(['consent:read'])]
    private ?bool $is_required = null;

    /**
     * @var Collection<int, Purchase>
     */
    #[ORM\ManyToMany(targetEntity: Purchase::class, inversedBy: 'Consents')]
    private Collection $purchases;

    #[Gedmo\Locale]
    private $locale;

    public function __construct()
    {
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

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function isIsRequired(): ?bool
    {
        return $this->is_required;
    }

    public function setIsRequired(bool $is_required): static
    {
        $this->is_required = $is_required;

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
        }

        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        $this->purchases->removeElement($purchase);

        return $this;
    }

}
