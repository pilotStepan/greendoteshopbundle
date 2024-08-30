<?php

namespace Greendot\EshopBundle\Entity\Project;

use Greendot\EshopBundle\Repository\Project\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'p_file')]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 150)]
    private $name;

    #[ORM\Column(type: 'string', length: 40)]
    private $extension;

    #[ORM\Column(type: 'string', length: 40)]
    private $mime;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $created;

    #[ORM\Column(type: 'string', length: 255)]
    private $path;

    #[ORM\Column(type: 'string', length: 150)]
    private $class_type;

    #[ORM\OneToMany(targetEntity: CategoryFile::class, mappedBy: 'file')]
    private $categoryFiles;

    #[ORM\ManyToMany(targetEntity: Comment::class, mappedBy: 'file')]
    private Collection $comments;

    #[ORM\ManyToOne(targetEntity: Review::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Review $review = null;

    public function __construct()
    {
        $this->categoryFiles = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

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

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(string $mime): self
    {
        $this->mime = $mime;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(?\DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getClassType(): ?string
    {
        return $this->class_type;
    }

    public function setClassType(string $class_type): self
    {
        $this->class_type = $class_type;
        return $this;
    }

    /**
     * @return Collection|CategoryFile[]
     */
    public function getCategoryFiles(): Collection
    {
        return $this->categoryFiles;
    }

    public function addCategoryFile(CategoryFile $categoryFile): self
    {
        if (!$this->categoryFiles->contains($categoryFile)) {
            $this->categoryFiles[] = $categoryFile;
            $categoryFile->setFile($this);
        }

        return $this;
    }

    public function removeCategoryFile(CategoryFile $categoryFile): self
    {
        if ($this->categoryFiles->removeElement($categoryFile)) {
            if ($categoryFile->getFile() === $this) {
                $categoryFile->setFile(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->addFile($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            $comment->removeFile($this);
        }

        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): self
    {
        $this->review = $review;
        return $this;
    }
}
