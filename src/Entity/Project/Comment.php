<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $submitted = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    private ?Client $client = null;

    #[ORM\Column]
    private ?bool $isAdmin = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'underComment')]
    private ?self $comment = null;

    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: self::class)]
    private Collection $underComment;

    #[ORM\ManyToMany(targetEntity: File::class, inversedBy: 'comments')]
    private Collection $file;

    public function __construct()
    {
        $this->underComment = new ArrayCollection();
        $this->file = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSubmitted(): ?\DateTimeInterface
    {
        return $this->submitted;
    }

    public function setSubmitted(\DateTimeInterface $submitted): self
    {
        $this->submitted = $submitted;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function isIsAdmin(): ?bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): self
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getComment(): ?self
    {
        return $this->comment;
    }

    public function setComment(?self $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getUnderComment(): Collection
    {
        return $this->underComment;
    }

    public function addUnderComment(self $underComment): self
    {
        if (!$this->underComment->contains($underComment)) {
            $this->underComment->add($underComment);
            $underComment->setComment($this);
        }

        return $this;
    }

    public function removeUnderComment(self $underComment): self
    {
        if ($this->underComment->removeElement($underComment)) {
            // set the owning side to null (unless already changed)
            if ($underComment->getComment() === $this) {
                $underComment->setComment(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getFile(): Collection
    {
        return $this->file;
    }

    public function addFile(File $file): self
    {
        if (!$this->file->contains($file)) {
            $this->file->add($file);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        $this->file->removeElement($file);

        return $this;
    }
}
