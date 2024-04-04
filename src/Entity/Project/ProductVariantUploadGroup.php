<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\ProductVariantUploadGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductVariantUploadGroupRepository::class)]
#[ApiResource]
class ProductVariantUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productVariantUploadGroups')]
    private ?UploadGroup $UploadGroup = null;

    #[ORM\ManyToOne(inversedBy: 'productVariantUploadGroups')]
    private ?ProductVariant $ProductVariant = null;

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

    public function getProductVariant(): ?ProductVariant
    {
        return $this->ProductVariant;
    }

    public function setProductVariant(?ProductVariant $ProductVariant): self
    {
        $this->ProductVariant = $ProductVariant;

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
