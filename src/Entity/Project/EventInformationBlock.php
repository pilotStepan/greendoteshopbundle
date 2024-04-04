<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\EventInformationBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventInformationBlockRepository::class)]
class EventInformationBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'eventInformationBlocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InformationBlock $informationBlock = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

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

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;

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
