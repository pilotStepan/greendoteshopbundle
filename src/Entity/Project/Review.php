<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'datetime')]
    private $date;

    #[ORM\Column(type: 'text')]
    private $contents;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $reviewer_name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $reviewer_email;

    #[ORM\Column(type: 'integer')]
    private $stars;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private $is_approved;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private $Product;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getContents(): ?string
    {
        return $this->contents;
    }

    public function setContents(string $contents): self
    {
        $this->contents = $contents;

        return $this;
    }

    public function getReviewerName(): ?string
    {
        return $this->reviewer_name;
    }

    public function setReviewerName(?string $reviewer_name): self
    {
        $this->reviewer_name = $reviewer_name;

        return $this;
    }

    public function getReviewerEmail(): ?string
    {
        return $this->reviewer_email;
    }

    public function setReviewerEmail(?string $reviewer_email): self
    {
        $this->reviewer_email = $reviewer_email;

        return $this;
    }

    public function getStars(): ?int
    {
        return $this->stars;
    }

    public function setStars(int $stars): self
    {
        $this->stars = $stars;

        return $this;
    }

    public function getIsApproved(): ?bool
    {
        return $this->is_approved;
    }

    public function setIsApproved(bool $is_approved): self
    {
        $this->is_approved = $is_approved;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->Product;
    }

    public function setProduct(?Product $Product): self
    {
        $this->Product = $Product;

        return $this;
    }
}
