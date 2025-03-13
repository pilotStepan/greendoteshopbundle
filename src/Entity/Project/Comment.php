<?php

namespace Greendot\EshopBundle\Entity\Project;

use Gedmo\Mapping\Annotation\Slug;
use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SebastianBergmann\CodeCoverage\Report\Xml\Project;

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

    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'comments')]
    private Collection $categories;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'underComment')]
    private ?self $comment = null;

    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: self::class)]
    private Collection $underComment;

    #[ORM\ManyToMany(targetEntity: File::class, inversedBy: 'comments')]
    private Collection $file;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: Product::class, inversedBy: 'comments')]
    private Collection $products;

    #[ORM\Column(type: "boolean")]
    private bool $isActive = false;

    #[ORM\Column(length: 255, unique: true )]
    #[Slug(fields: ['title'])]
    private ?string $slug = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->underComment = new ArrayCollection();
        $this->file = new ArrayCollection();
        $this->products = new ArrayCollection();
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

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        $this->categories->removeElement($category);

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

    public function getEmail() : ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getName() : ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);

        return $this;
    }

    public  function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSlug() : ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

}
