<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\PersonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonRepository::class)]
class Person
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $surname = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $room = null;

    #[ORM\Column(nullable: true)]
    private ?int $userID = null;

    #[ORM\OneToMany(mappedBy: 'person', targetEntity: CategoryPerson::class)]
    private Collection $category;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleBefore = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleAfter = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'person', targetEntity: Parameter::class)]
    private Collection $parameters;

    #[ORM\OneToMany(mappedBy: 'Person', targetEntity: PersonUploadGroup::class)]
    private Collection $personUploadGroups;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    public function __construct()
    {
        $this->category = new ArrayCollection();
        $this->personUploadGroups = new ArrayCollection();
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

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): self
    {
        $this->surname = $surname;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): self
    {
        $this->department = $department;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getRoom(): ?string
    {
        return $this->room;
    }

    public function setRoom(?string $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getUserID(): ?int
    {
        return $this->userID;
    }

    public function setUserID(?int $userID): self
    {
        $this->userID = $userID;

        return $this;
    }

    /**
     * @return Collection<int, CategoryPerson>
     */
    public function getCategory(): Collection
    {
        return $this->category;
    }

    public function addCategory(CategoryPerson $category): self
    {
        if (!$this->category->contains($category)) {
            $this->category->add($category);
            $category->setPerson($this);
        }

        return $this;
    }

    public function removeCategory(CategoryPerson $category): self
    {
        if ($this->category->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getPerson() === $this) {
                $category->setPerson(null);
            }
        }

        return $this;
    }

    public function getTitleBefore(): ?string
    {
        return $this->titleBefore;
    }

    public function setTitleBefore(?string $titleBefore): self
    {
        $this->titleBefore = $titleBefore;

        return $this;
    }

    public function getTitleAfter(): ?string
    {
        return $this->titleAfter;
    }

    public function setTitleAfter(?string $titleAfter): self
    {
        $this->titleAfter = $titleAfter;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, PersonUploadGroup>
     */
    public function getPersonUploadGroups(): Collection
    {
        return $this->personUploadGroups;
    }

    public function addPersonUploadGroup(PersonUploadGroup $personUploadGroup): self
    {
        if (!$this->personUploadGroups->contains($personUploadGroup)) {
            $this->personUploadGroups->add($personUploadGroup);
            $personUploadGroup->setPerson($this);
        }

        return $this;
    }

    public function removePersonUploadGroup(PersonUploadGroup $personUploadGroup): self
    {
        if ($this->personUploadGroups->removeElement($personUploadGroup)) {
            // set the owning side to null (unless already changed)
            if ($personUploadGroup->getPerson() === $this) {
                $personUploadGroup->setPerson(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Parameter>
     */
    public function getParameters(): Collection
    {
        return $this->parameters;
    }

    public function addParameter(Parameter $parameter): self
    {
        if (!$this->parameters->contains($parameter)) {
            $this->parameters->add($parameter);
            $parameter->setPerson($this);
        }

        return $this;
    }

    public function removeParameter(Parameter $parameter): self
    {
        if ($this->parameters->removeElement($parameter)) {
            // set the owning side to null (unless already changed)
            if ($parameter->getPerson() === $this) {
                $parameter->setPerson(null);
            }
        }

        return $this;
    }
}
