<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Greendot\EshopBundle\ApiResource\ProductReviews;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Greendot\EshopBundle\StateProvider\ReviewStatsStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/reviews-stats',
            name: 'reviews_stats',
            provider: ReviewStatsStateProvider::class,
        ),

        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['review:read']],
    denormalizationContext: ['groups' => ['review:write']],
    order: ['date' => 'DESC']
)]
#[ApiFilter(ProductReviews::class)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['review:read'])]
    private $id;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['review:read'])]
    private \DateTime $date;

    #[ORM\Column(type: 'text')]
    #[Groups(['review:read', 'review:write'])]
    private ?string $contents;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['review:read', 'review:write'])]
    private ?string $reviewer_name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reviewer_email;

    #[ORM\Column(type: 'integer')]
    #[Groups(['review:read', 'review:write'])]
    private ?int $stars;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $is_approved;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reviews')]
    #[Groups(['review:read', 'review:write'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $Product;

    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'review')]
    private Collection $files;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['review:read', 'review:write'])]
    private ?bool $positive;

    /**
     * Holds any additional parameters for this review.
     * We may want to implement validation rules for this field if necessary.
     */
    // #[ValidReviewParameters]
    #[ORM\Column(type: 'json', length: 255, nullable: true)]
    #[Groups(['review:read', 'review:write'])]
    private ?array $review_parameters;

    #[ORM\OneToMany(targetEntity: ReviewPoints::class, mappedBy: 'review', cascade: ['persist', 'remove'])]
    #[Groups(['review:read', 'review:write'])]
    private Collection $reviewPoints;

    #[ORM\Column(type: "boolean")]
    #[Groups(['comment:read', 'comment:write'])]
    private bool $isRead = false;

    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->reviewPoints = new ArrayCollection();
        $this->date = new \DateTime();
        $this->is_approved = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
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

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setReview($this);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        if ($this->files->removeElement($file)) {
            if ($file->getReview() === $this) {
                $file->setReview(null);
            }
        }

        return $this;
    }

    public function getPositive(): ?bool
    {
        return $this->positive;
    }

    public function setPositive(bool $positive): self
    {
        $this->positive = $positive;
        return $this;
    }

    public function getReviewParameters(): ?array
    {
        return $this->review_parameters;
    }

    public function setReviewParameters(?array $review_parameters): self
    {
        $this->review_parameters = $review_parameters;
        return $this;
    }

    public function getReviewPoints(): Collection
    {
        return $this->reviewPoints;
    }

    public function setReviewPoints(Collection $reviewPoints): void
    {
        $this->reviewPoints = $reviewPoints;
    }

    public function addReviewPoint(ReviewPoints $point): self
    {
        if (!$this->reviewPoints->contains($point)) {
            $this->reviewPoints[] = $point;
            $point->setReview($this);
        }
        return $this;
    }

    public function removeReviewPoint(ReviewPoints $point): self
    {
        if ($this->reviewPoints->removeElement($point)) {
            if ($point->getReview() === $this) {
                $point->setReview(null);
            }
        }
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getIsRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }
}
