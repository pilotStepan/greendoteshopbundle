<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org UnitPriceSpecification model.
 * @link https://schema.org/UnitPriceSpecification
 */
class UnitPriceSpecification extends AbstractSchemaType
{
    protected ?float $price = null;
    protected ?string $priceCurrency = null;
    protected ?bool $valueAddedTaxIncluded = null;
    protected ?string $priceType = null;

    public function getType(): string
    {
        return 'UnitPriceSpecification';
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

    public function getValueAddedTaxIncluded(): ?bool { return $this->valueAddedTaxIncluded; }

    public function setValueAddedTaxIncluded(?bool $valueAddedTaxIncluded): self
    {
        $this->valueAddedTaxIncluded = $valueAddedTaxIncluded;
        return $this;
    }

    public function getPriceType(): ?string { return $this->priceType; }

    public function setPriceType(?string $priceType): self
    {
        $this->priceType = $priceType;
        return $this;
    }
}
