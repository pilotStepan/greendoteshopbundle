<?php

namespace Greendot\EshopBundle\Entity\Project;

use DateTimeInterface;
use Greendot\EshopBundle\Repository\Project\ConversionRateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversionRateRepository::class)]
class ConversionRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $created;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $validFrom;

    #[ORM\ManyToOne(targetEntity: Currency::class, cascade: ['persist'], inversedBy: 'conversionRates')]
    private Currency $currency;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCreated(DateTimeInterface $created) : self 
    {
        $this->created = $created;

        return $this;
    }

    public function getCreated() : DateTimeInterface
    {
        return $this->created;
    }

    public function setValidFrom(DateTimeInterface $validFrom) : self
    {
        $this->validFrom = $validFrom;    

        return $this;
    }

    public function getValidFrom() : DateTimeInterface 
    {
        return $this->validFrom;    
    }

    public function setCurrency(Currency $currency) : self 
    {
        $this->currency = $currency;    
        
        return $this;
    }

    public function getCurrency() : Currency 
    {
        return $this->currency;    
    }
}
