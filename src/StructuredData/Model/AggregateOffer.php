<?php

namespace Greendot\EshopBundle\StructuredData\Model;

/**
 * Schema.org AggregateOffer model.
 * @link https://schema.org/AggregateOffer
 */
class AggregateOffer extends AbstractSchemaType
{
    protected ?float $lowPrice = null;
    protected ?float $highPrice = null;
    protected ?string $priceCurrency = null;
    protected ?int $offerCount = null;
    /** @var Offer[]|null */
    protected ?array $offers = null;

    public function getType(): string
    {
        return 'AggregateOffer';
    }

    public function getLowPrice(): ?float { return $this->lowPrice; }
    public function setLowPrice(?float $lowPrice): self { $this->lowPrice = $lowPrice; return $this; }

    public function getHighPrice(): ?float { return $this->highPrice; }
    public function setHighPrice(?float $highPrice): self { $this->highPrice = $highPrice; return $this; }

    public function getPriceCurrency(): ?string { return $this->priceCurrency; }
    public function setPriceCurrency(?string $priceCurrency): self { $this->priceCurrency = $priceCurrency; return $this; }

    public function getOfferCount(): ?int { return $this->offerCount; }
    public function setOfferCount(?int $offerCount): self { $this->offerCount = $offerCount; return $this; }

    /**
     * @return Offer[]|null
     */
    public function getOffers(): ?array { return $this->offers; }

    /**
     * @param Offer[]|null $offers
     * @return $this
     */
    public function setOffers(?array $offers): self { $this->offers = $offers; return $this; }
}
