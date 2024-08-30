<?php

namespace Greendot\EshopBundle\Entity\Project;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\ApiResource\ProductReviews;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
#[ApiResource(
    normalizationContext: ['groups' => ['review:read']],
    denormalizationContext: ['groups' => ['review:write']]
)]
#[ApiFilter(ProductReviews::class)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['review:read', 'review:write'])]
    private $date;

    #[ORM\Column(type: 'text')]
    #[Groups(['review:read', 'review:write'])]
    private $contents;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['review:read', 'review:write'])]
    private $reviewer_name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $reviewer_email;

    #[ORM\Column(type: 'integer')]
    #[Groups(['review:read', 'review:write'])]
    private $stars;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['review:read', 'review:write'])]
    private $is_approved;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reviews')]
    #[Groups(['review:read', 'review:write'])]
    #[ORM\JoinColumn(nullable: false)]
    private $Product;

    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'review')]
    private Collection $files;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['review:read', 'review:write'])]
    private $positive;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['review:read', 'review:write'])]
    private $review_parameters;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewPoints::class, cascade: ['persist', 'remove'])]
    #[Groups(['review:read'])]
    private Collection $reviewPoints;

    public function getReviewPoints(): Collection
    {
        return $this->reviewPoints;
    }

    public function setReviewPoints(Collection $reviewPoints): void
    {
        $this->reviewPoints = $reviewPoints;
    }

    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->reviewPoints = new ArrayCollection();
    }

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

    public function getReviewParameters()
    {
        return $this->review_parameters;
    }

    public function setReviewParameters($review_parameters): void
    {
        $this->review_parameters = $review_parameters;
    }
}
