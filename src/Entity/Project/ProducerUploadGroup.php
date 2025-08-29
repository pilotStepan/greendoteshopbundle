<?php

namespace Greendot\EshopBundle\Entity\Project;

use Doctrine\ORM\Mapping as ORM;
use Greendot\EshopBundle\Repository\Project\ProducerUploadGroupRepository;

#[ORM\Entity(repositoryClass: ProducerUploadGroupRepository::class)]
class ProducerUploadGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'producerUploadGroups')]
    private ?UploadGroup $uploadGroup = null;

    #[ORM\ManyToOne(inversedBy: 'producerUploadGroups')]
    private ?Producer $producer = null;

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

    public function getProducer(): ?Producer
    {
        return $this->producer;
    }

    public function setProducer(?Producer $producer): static
    {
        $this->producer = $producer;

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
