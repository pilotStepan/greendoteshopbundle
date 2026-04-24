<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Entity\Project\InformationBlock;
use Greendot\EshopBundle\Entity\Project\UploadGroup;
use Greendot\EshopBundle\Repository\Project\InformationBlockUploadGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InformationBlockUploadGroupRepository::class)]
class InformationBlockUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'informationBlockUploadGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UploadGroup $uploadGroup = null;

    #[ORM\ManyToOne(inversedBy: 'informationBlockUploadGroups')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InformationBlock $informationBlock = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUploadGroup(): ?UploadGroup
    {
        return $this->uploadGroup;
    }

    public function setUploadGroup(?UploadGroup $uploadGroup): static
    {
        $this->uploadGroup = $uploadGroup;

        return $this;
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
