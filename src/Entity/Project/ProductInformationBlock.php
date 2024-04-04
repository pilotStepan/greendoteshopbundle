<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\ProductInformationBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductInformationBlockRepository::class)]
class ProductInformationBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productInformationBlocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InformationBlock $informationBlock = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInformationBlock(): ?InformationBlock
    {
        return $this->informationBlock;
    }

    public function setInformationBlock(?InformationBlock $informationBlock): static
    {
        $this->informationBlock = $informationBlock;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
