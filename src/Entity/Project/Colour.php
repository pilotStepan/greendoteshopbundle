<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Core\Annotation\ApiResource;
use Greendot\EshopBundle\Repository\Project\ColourRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ColourRepository::class)]
class Colour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["SearchProductResultApiModel"])]
    private $id;

    #[Groups(['product_item:read', 'product_list:read', 'product_variant:read', 'colour:read', 'colour:write', "SearchProductResultApiModel"])]
    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[Groups(['product_item:read', 'product_list:read', 'product_variant:read', 'colour:read', 'colour:write', "SearchProductResultApiModel"])]
    #[ORM\Column(type: 'string', length: 255)]
    private $hex;

    #[ORM\Column(type: 'integer')]
    #[Groups(['product_item:read', 'product_list:read', 'product_variant:read', 'colour:read', 'colour:write', "SearchProductResultApiModel"])]
    private $sequence;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;


    public function __construct()
    {
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

    public function getHex(): ?string
    {
        return $this->hex;
    }

    public function setHex(string $hex): self
    {
        $this->hex = $hex;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

}
