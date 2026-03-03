<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org Product model.
 * @link https://schema.org/Product
 */
class Product extends AbstractSchemaType
{
    protected ?string $name = null;
    protected ?string $description = null;
    protected ?string $sku = null;
    protected ?string $mpn = null;
    protected ?string $url = null;
    /** @var string|array|null */
    protected $image = null;
    /** @var Brand|string|null */
    protected $brand = null;
    /** @var Offer|AggregateOffer|array|null */
    protected $offers = null;
    /** @var AggregateRating|null */
    protected ?AggregateRating $aggregateRating = null;
    /** @var Review|array|null */
    protected $review = null;
    /** @var array|null */
    protected ?array $additionalProperty = null;
    protected ?string $color = null;
    protected ?string $material = null;
    /** @var mixed|null */
    protected $width = null;
    /** @var mixed|null */
    protected $height = null;

    public function getType(): string
    {
        return 'Product';
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getSku(): ?string { return $this->sku; }
    public function setSku(?string $sku): self { $this->sku = $sku; return $this; }

    public function getMpn(): ?string { return $this->mpn; }
    public function setMpn(?string $mpn): self { $this->mpn = $mpn; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }

    public function getImage() { return $this->image; }
    public function setImage($image): self { $this->image = $image; return $this; }

    public function getBrand() { return $this->brand; }
    public function setBrand($brand): self { $this->brand = $brand; return $this; }

    public function getOffers() { return $this->offers; }
    public function setOffers($offers): self { $this->offers = $offers; return $this; }

    public function getAggregateRating(): ?AggregateRating { return $this->aggregateRating; }
    public function setAggregateRating(?AggregateRating $aggregateRating): self { $this->aggregateRating = $aggregateRating; return $this; }

    public function getReview() { return $this->review; }
    public function setReview($review): self { $this->review = $review; return $this; }

    public function getAdditionalProperty(): ?array { return $this->additionalProperty; }
    public function setAdditionalProperty(?array $additionalProperty): self { $this->additionalProperty = $additionalProperty; return $this; }

    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }

    public function getMaterial(): ?string { return $this->material; }
    public function setMaterial(?string $material): self { $this->material = $material; return $this; }

    public function getWidth() { return $this->width; }
    public function setWidth($width): self { $this->width = $width; return $this; }

    public function getHeight() { return $this->height; }
    public function setHeight($height): self { $this->height = $height; return $this; }
}
