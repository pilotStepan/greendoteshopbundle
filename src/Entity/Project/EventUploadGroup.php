<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\Repository\Project\EventUploadGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventUploadGroupRepository::class)]
#[ApiResource]
class EventUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'eventUploadGroups')]
    private ?UploadGroup $UploadGroup = null;

    #[ORM\ManyToOne(inversedBy: 'eventUploadGroups')]
    private ?Event $Event = null;

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

    public function getEvent(): ?Event
    {
        return $this->Event;
    }

    public function setEvent(?Event $Event): self
    {
        $this->Event = $Event;

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
