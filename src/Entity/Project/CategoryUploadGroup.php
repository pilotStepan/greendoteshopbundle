<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\CategoryUploadGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategoryUploadGroupRepository::class)]
#[ApiResource]
class CategoryUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'categoryUploadGroups')]
    #[Groups(['category_default', 'category:read', 'category:write'])]
    private ?UploadGroup $UploadGroup = null;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'categoryUploadGroups')]
    private ?Category $Category = null;

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

    public function getCategory(): ?Category
    {
        return $this->Category;
    }

    public function setCategory(?Category $Category): self
    {
        $this->Category = $Category;

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
