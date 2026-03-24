<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org Review model.
 * @link https://schema.org/Review
 */
class Review extends AbstractSchemaType
{
    protected ?string $reviewBody = null;
    /** @var AggregateRating|null */
    protected ?AggregateRating $reviewRating = null;
    protected ?string $author = null;
    protected ?string $datePublished = null;

    public function getType(): string
    {
        return 'Review';
    }

    public function getReviewBody(): ?string { return $this->reviewBody; }

    public function setReviewBody(?string $reviewBody): self
    {
        $this->reviewBody = $reviewBody;
        return $this;
    }

    public function getReviewRating(): ?AggregateRating { return $this->reviewRating; }

    public function setReviewRating(?AggregateRating $reviewRating): self
    {
        $this->reviewRating = $reviewRating;
        return $this;
    }

    public function getAuthor(): ?string { return $this->author; }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getDatePublished(): ?string { return $this->datePublished; }

    public function setDatePublished(?string $datePublished): self
    {
        $this->datePublished = $datePublished;
        return $this;
    }
}
