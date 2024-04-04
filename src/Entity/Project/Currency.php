<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 15)]
    private ?string $symbol = null;

    #[ORM\Column]
    private ?float $conversionRate = null;

    #[ORM\Column]
    private ?int $rounding = null;

    #[ORM\Column]
    private ?bool $isDefault = null;

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

    public function getConversionRate(): ?float
    {
        return $this->conversionRate;
    }

    public function setConversionRate(float $conversionRate): self
    {
        $this->conversionRate = $conversionRate;

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
}
