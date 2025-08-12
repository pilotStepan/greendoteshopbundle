<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\BranchTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BranchTypeRepository::class)]
class BranchType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['branch:read', 'branch:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;



    /**
     * @var Collection<int, Branch>
     */
    #[ORM\OneToMany(targetEntity: Branch::class, mappedBy: 'BranchType')]
    private Collection $Branch;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['branch:read', 'branch:write'])]
    private ?string $icon = null;

    public function __construct()
    {
        $this->Branch = new ArrayCollection();
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
     * @return Collection<int, Branch>
     */
    public function getBranch(): Collection
    {
        return $this->Branch;
    }

    public function addBranch(Branch $branch): static
    {
        if (!$this->Branch->contains($branch)) {
            $this->Branch->add($branch);
            $branch->setBranchType($this);
        }

        return $this;
    }

    public function removeBranch(Branch $branch): static
    {
        if ($this->Branch->removeElement($branch)) {
            // set the owning side to null (unless already changed)
            if ($branch->getBranchType() === $this) {
                $branch->setBranchType(null);
            }
        }

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }
}
