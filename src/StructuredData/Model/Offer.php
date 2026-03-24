<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org Offer model.
 * @link https://schema.org/Offer
 */
class Offer extends AbstractSchemaType
{
    protected ?float $price = null;
    protected ?string $priceCurrency = null;
    protected ?string $availability = null;
    protected ?string $url = null;
    protected ?string $priceValidUntil = null;
    protected ?string $itemCondition = 'https://schema.org/NewCondition';
    /** @var UnitPriceSpecification|array|null */
    protected $priceSpecification = null;

    public function getType(): string
    {
        return 'Offer';
    }

    public function getPrice(): ?float { return $this->price; }

    public function setPrice(?float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getPriceCurrency(): ?string { return $this->priceCurrency; }

    public function setPriceCurrency(?string $priceCurrency): self
    {
        $this->priceCurrency = $priceCurrency;
        return $this;
    }

    public function getAvailability(): ?string { return $this->availability; }

    public function setAvailability(?string $availability): self
    {
        $this->availability = $availability;
        return $this;
    }

    public function getUrl(): ?string { return $this->url; }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getPriceValidUntil(): ?string { return $this->priceValidUntil; }

    public function setPriceValidUntil(?string $priceValidUntil): self
    {
        $this->priceValidUntil = $priceValidUntil;
        return $this;
    }

    public function getItemCondition(): ?string { return $this->itemCondition; }

    public function setItemCondition(?string $itemCondition): self
    {
        $this->itemCondition = $itemCondition;
        return $this;
    }

    public function getPriceSpecification() { return $this->priceSpecification; }

    public function setPriceSpecification($priceSpecification): self
    {
        $this->priceSpecification = $priceSpecification;
        return $this;
    }
}
