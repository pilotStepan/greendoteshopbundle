<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\VideoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
class Video
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["SearchProductResultApiModel"])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["SearchProductResultApiModel"])]
    private $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["SearchProductResultApiModel"])]
    private $url;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["SearchProductResultApiModel"])]
    private $thumbnail_url;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["SearchProductResultApiModel"])]
    private $service;

    #[ORM\Column(type: 'integer')]
    #[Groups(["SearchProductResultApiModel"])]
    private $sequence;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class, inversedBy: 'video')]
    private $productVariant;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnail_url;
    }

    public function setThumbnailUrl(?string $thumbnail_url): self
    {
        $this->thumbnail_url = $thumbnail_url;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductVariant $productVariant): self
    {
        $this->productVariant = $productVariant;

        return $this;
    }
}
