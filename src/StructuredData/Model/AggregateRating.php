<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org AggregateRating model.
 * @link https://schema.org/AggregateRating
 */
class AggregateRating extends AbstractSchemaType
{
    protected ?float $ratingValue = null;
    protected ?int $reviewCount = null;
    protected ?int $ratingCount = null;
    protected ?float $bestRating = null;
    protected ?float $worstRating = null;

    public function getType(): string
    {
        return 'AggregateRating';
    }

    public function getRatingValue(): ?float { return $this->ratingValue; }
    public function setRatingValue(?float $ratingValue): self { $this->ratingValue = $ratingValue; return $this; }

    public function getReviewCount(): ?int { return $this->reviewCount; }
    public function setReviewCount(?int $reviewCount): self { $this->reviewCount = $reviewCount; return $this; }

    public function getRatingCount(): ?int { return $this->ratingCount; }
    public function setRatingCount(?int $ratingCount): self { $this->ratingCount = $ratingCount; return $this; }

    public function getBestRating(): ?float { return $this->bestRating; }
    public function setBestRating(?float $bestRating): self { $this->bestRating = $bestRating; return $this; }

    public function getWorstRating(): ?float { return $this->worstRating; }
    public function setWorstRating(?float $worstRating): self { $this->worstRating = $worstRating; return $this; }
}
