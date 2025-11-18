<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Repository\Project\ParameterGroupFormatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ParameterGroupFormatRepository::class)]
class ParameterGroupFormat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['parameter_filtered:read', 'parameter:read', 'parameter_group:read', 'category_parameter_group:read', 'product_variant:read', 'product_item:read', 'product_list:read', 'comment:read', 'purchase:read', 'purchase:wishlist'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['parameter_filtered:read', 'parameter:read', 'parameter_group:read', 'category_parameter_group:read', 'product_variant:read', 'product_item:read', 'product_list:read', 'comment:read', 'purchase:read', 'purchase:wishlist'])]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'parameterGroupFormat', targetEntity: ParameterGroup::class)]
    private Collection $parameterGroup;

    public function __construct()
    {
        $this->parameterGroup = new ArrayCollection();
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

    /**
     * @return Collection<int, ParameterGroup>
     */
    public function getParameterGroup(): Collection
    {
        return $this->parameterGroup;
    }

    public function addParameterGroup(ParameterGroup $parameterGroup): static
    {
        if (!$this->parameterGroup->contains($parameterGroup)) {
            $this->parameterGroup->add($parameterGroup);
            $parameterGroup->setParameterGroupFormat($this);
        }

        return $this;
    }

    public function removeParameterGroup(ParameterGroup $parameterGroup): static
    {
        if ($this->parameterGroup->removeElement($parameterGroup)) {
            // set the owning side to null (unless already changed)
            if ($parameterGroup->getParameterGroupFormat() === $this) {
                $parameterGroup->setParameterGroupFormat(null);
            }
        }

        return $this;
    }
}
