<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\CategoryInformationBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryInformationBlockRepository::class)]
class CategoryInformationBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'categoryInformationBlocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InformationBlock $informationBlock = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(?int $sequence): static
    {
        $this->sequence = $sequence;

        return $this;
    }
}
