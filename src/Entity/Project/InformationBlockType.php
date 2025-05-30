<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\InformationBlockTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InformationBlockTypeRepository::class)]
class InformationBlockType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_item:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product_item:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $template = null;

    #[ORM\OneToMany(mappedBy: 'InformationBlockType', targetEntity: InformationBlock::class)]
    private Collection $informationBlocks;

    public function __construct()
    {
        $this->informationBlocks = new ArrayCollection();
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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): static
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return Collection<int, InformationBlock>
     */
    public function getInformationBlocks(): Collection
    {
        return $this->informationBlocks;
    }

    public function addInformationBlock(InformationBlock $informationBlock): static
    {
        if (!$this->informationBlocks->contains($informationBlock)) {
            $this->informationBlocks->add($informationBlock);
            $informationBlock->setInformationBlockType($this);
        }

        return $this;
    }

    public function removeInformationBlock(InformationBlock $informationBlock): static
    {
        if ($this->informationBlocks->removeElement($informationBlock)) {
            // set the owning side to null (unless already changed)
            if ($informationBlock->getInformationBlockType() === $this) {
                $informationBlock->setInformationBlockType(null);
            }
        }

        return $this;
    }
}
