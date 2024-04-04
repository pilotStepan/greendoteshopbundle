<?php

namespace App\Entity;

use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use App\Repository\ExternalIdsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExternalIdsRepository::class)]
class ExternalIds
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    private ?string $externalId = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?ParameterGroup $parameterGroup = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?int
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getParameterGroup(): ?ParameterGroup
    {
        return $this->parameterGroup;
    }

    public function setParameterGroup(ParameterGroup $parameterGroup): self
    {
        $this->parameterGroup = $parameterGroup;

        return $this;
    }

}
