<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\Entity\Interface\SoftDeletedInterface;
use Greendot\EshopBundle\Entity\Trait\SoftDeletedTrait;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ApiResource(
    normalizationContext: ['currency:read'],
    denormalizationContext: ['currency:write'],
    paginationEnabled: false
)]
class Currency implements SoftDeletedInterface
{
    use SoftDeletedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 15)]
    private ?string $symbol = null;

    #[ORM\OneToMany(targetEntity: ConversionRate::class, cascade: ['persist'], mappedBy: 'currency')]
    private Collection $conversionRates;

    #[ORM\Column]
    private ?int $rounding = null;

    #[ORM\Column]
    private ?bool $isDefault = null;

    #[ORM\Column]
    private ?string $defaultLocale = null;
    
    #[ORM\Column(options: ["default" => false])]
    private ?bool $is_symbol_left = true;

    public function __construct()
    {
        $this->conversionRates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getConversionRates(): Collection
    {
        return $this->conversionRates;
    }

    public function addConversionRate(ConversionRate $conversionRate): self
    {
        if (!$this->conversionRates->contains($conversionRate)) {
            $this->conversionRates->add($conversionRate);
            $conversionRate->setCurrency($this);
        }

        return $this;
    }

    public function removeConversionRate(ConversionRate $conversionRate): self
    {
        if ($this->conversionRates->removeElement($conversionRate)) {
            // if ($conversionRate->getCurrency() === $this) {
            //      $conversionRate->setCurrency(null);
            // }
        }

        return $this;
    }

    public function getRounding(): ?int
    {
        return $this->rounding;
    }

    public function setRounding(int $rounding): self
    {
        $this->rounding = $rounding;

        return $this;
    }

    public function isIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function isSymbolLeft(): ?bool
    {
        return $this->is_symbol_left;
    }

    public function setSymbolLeft(bool $is_symbol_left): static
    {
        $this->is_symbol_left = $is_symbol_left;

        return $this;
    }
}
