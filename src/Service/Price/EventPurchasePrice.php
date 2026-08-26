<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;

class EventPurchasePrice
{

    /**
     * @var EventPrice[]
     */
    private array $eventPrices = [];
    private ?float $purchasePrice = null;
    private ?float $minPrice = null;

    private float $discountValue = 0;
    private float $discountPercentage = 0;

    public function __construct(
        private Purchase                       $purchase,
        private VatCalculationType             $vatCalculationType,
        private DiscountCalculationType        $discountCalculationType,
        private Currency                       $currency,
        private ConversionRate                 $conversionRate,
        private readonly EventPriceFactory     $eventPriceFactory,
        private readonly PriceUtils            $priceUtils,
    )
    {
        $this->loadEvents();
    }

    public function getPrice(): ?float
    {
        return $this->priceUtils->convertCurrency($this->purchasePrice, $this->currency, $this->conversionRate);
    }

    /**
     * @return float|null
     */
    public function getMinPrice(): ?float
    {
        return $this->priceUtils->convertCurrency($this->minPrice, $this->currency, $this->conversionRate);
    }

    public function getDiscountValue() : float
    {
        return $this->discountValue;
    }

    public function getDiscountPercentage() : float
    {
        return $this->discountPercentage;
    }

    /**
     * @param VatCalculationType $vatCalculationType
     * @param bool $force - Forces VatCalculationType change event when isVatExempted is true
     * @return $this
     */
    public function setVatCalculationType(VatCalculationType $vatCalculationType, bool $force = false): self
    {
        if (!$this->purchase->isVatExempted() or $force){
            $this->vatCalculationType = $vatCalculationType;
            $this->recalculatePrices();
        }
        return $this;
    }

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): EventPurchasePrice
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculatePrices();
        return $this;
    }

    public function setCurrency(Currency $currency): EventPurchasePrice
    {
        $this->currency = $currency;
        $this->conversionRate = $this->priceUtils->getConversionRate($currency, $this->purchase);
        $this->recalculatePrices();
        return $this;
    }


    private function loadEvents(): void
    {
        foreach ($this->purchase->getPurchaseEvents() as $purchaseEvent) {
            $this->eventPrices [] = $this->eventPriceFactory->create($purchaseEvent, $this->currency, $this->vatCalculationType, $this->discountCalculationType, $this->conversionRate);
        }
        $this->loadPrice();
    }

    private function recalculatePrices(): void
    {
        foreach ($this->eventPrices as $eventPrice) {
            $eventPrice->setVatCalculationType($this->vatCalculationType);
            $eventPrice->setCurrency($this->currency);
            $eventPrice->setDiscountCalculationType($this->discountCalculationType);
        }
        $this->loadPrice();
    }

    private function loadPrice(): void
    {
        $price = null;
        $minPrice = null;
        foreach ($this->eventPrices as $eventPrice) {
            $price += $eventPrice->getPrice(true);
            $minPrice += $eventPrice->getMinPrice(true);
        }
        $this->purchasePrice = $price;
        $this->minPrice = $minPrice;
        $this->loadDiscountValue();
        $this->loadDiscountPercentage();
    }

    private function loadDiscountValue() : void
    {
        $eventDiscountSum = 0;
        foreach ($this->eventPrices as $eventPrice) {
            $eventDiscountSum += $eventPrice->getDiscountValue();
        }
        $this->discountValue = $eventDiscountSum;
    }

    private function loadDiscountPercentage() : void
    {
        $discountPercentageSum = 0;
        $discountPercentageCount = 0;
        foreach ($this->eventPrices as $eventPrice) {
            $discountPercentageSum += $eventPrice->getDiscountPercentage();
            $discountPercentageCount++;
        }

        $this->discountPercentage = $discountPercentageCount  > 0 ? $discountPercentageSum/$discountPercentageCount : 0;
    }

}
