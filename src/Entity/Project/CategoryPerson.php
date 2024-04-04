<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\CategoryPersonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryPersonRepository::class)]
class CategoryPerson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'persons')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'category')]
    private ?Person $person = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isManager = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(?Person $person): self
    {
        $this->person = $person;

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

    public function isIsManager(): ?bool
    {
        return $this->isManager;
    }

    public function setIsManager(?bool $isManager): self
    {
        $this->isManager = $isManager;

        return $this;
    }
}
