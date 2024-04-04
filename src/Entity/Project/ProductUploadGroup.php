<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\ProductUploadGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductUploadGroupRepository::class)]
#[ApiResource]
class ProductUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productUploadGroups')]
    private ?UploadGroup $UploadGroup = null;

    #[ORM\ManyToOne(inversedBy: 'productUploadGroups')]
    private ?Product $Product = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUploadGroup(): ?UploadGroup
    {
        return $this->UploadGroup;
    }

    public function setUploadGroup(?UploadGroup $UploadGroup): self
    {
        $this->UploadGroup = $UploadGroup;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->Product;
    }

    public function setProduct(?Product $Product): self
    {
        $this->Product = $Product;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }
}
