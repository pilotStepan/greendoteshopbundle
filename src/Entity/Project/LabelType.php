<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\LabelTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['labelType:read']],
    denormalizationContext: ['groups' => ['labelType:write']]
)]
#[ORM\Entity(repositoryClass: LabelTypeRepository::class)]
class LabelType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product_item:read', 'product_list:read', 'labelType:read', 'labelType:write', 'label:read', 'label:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['labelType:read', 'labelType:write', 'label:read', 'label:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['labelType:read', 'labelType:write', 'label:read', 'label:write'])]
    private ?string $color = null;

    /**
     * @var Collection<int, Label>
     */
    #[ORM\OneToMany(mappedBy: 'labelType', targetEntity: Label::class)]
    #[Groups(['labelType:read', 'labelType:write', 'label:read', 'label:write'])]
    private Collection $labels;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): static
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
            $label->setLabelType($this);
        }

        return $this;
    }

    public function removeLabel(Label $label): static
    {
        if ($this->labels->removeElement($label)) {
            if ($label->getLabelType() === $this) {
                $label->setLabelType(null);
            }
        }

        return $this;
    }
}
