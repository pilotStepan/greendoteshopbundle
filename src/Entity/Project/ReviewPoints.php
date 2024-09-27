<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Greendot\EshopBundle\ApiResource\ReviewPointsByReviewFilter;
use Greendot\EshopBundle\Repository\Project\ReviewPointsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ReviewPointsRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['review_points:read']],
    denormalizationContext: ['groups' => ['review_points:write']]
)]
#[ApiFilter(ReviewPointsByReviewFilter::class)]
class ReviewPoints
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['review:read', 'review_points:read', 'review_points:write'])]
    private ?bool $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['review:read', 'review_points:read', 'review_points:write'])]
    private ?string $text = null;

    #[ORM\ManyToOne(inversedBy: 'reviewPoints')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review_points:read', 'review_points:write'])]
    private ?Review $review = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isType(): ?bool
    {
        return $this->type;
    }

    public function setType(bool $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): static
    {
        $this->review = $review;

        return $this;
    }
}
