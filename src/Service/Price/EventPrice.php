<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\PurchaseEvent;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\DiscountCombinationStrategyInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Bundle\SecurityBundle\Security;

class EventPrice
{
    //phase 1 - always static after initialization
    private Event|PurchaseEvent $event;
    private ?float $clientDiscount = null;

    //always recalculate
    private ?float $price = null;

    private ?float $calculatedPrice = null;
    private ?float $minPrice = null;

    private ?float $vatValue = null;
    private ?float $vatPercentage = null;

    private ?float $discountValue = null;
    private ?float $discountPercentage = null;

    private ?\DateTime $discountValidUntil = null;
    private ?\DateTime $priceValidUntil = null;

    private VatCalculationType $vatCalculationType;
    private DiscountCalculationType $discountCalculationType;

    private Currency $currency;

    private bool $emptyPrice = false;

    public function __construct(
        Event|PurchaseEvent                            $event,
        Currency                                        $currency,
        private ConversionRate                          $conversionRate,
        VatCalculationType                              $vatCalculationType,
        DiscountCalculationType                         $discountCalculationType,
        private readonly int                            $afterRegistrationBonus,
        private readonly Security                       $security,
        private readonly PriceRepository                $priceRepository,
        private readonly DiscountService                $discountService,
        private readonly PriceUtils                     $priceUtils,
        private readonly DiscountCombinationStrategyInterface $discountCombinationStrategy,
        private readonly ?Price                          $priceEntity = null
    )
    {
        $this->vatCalculationType = $vatCalculationType;
        $this->discountCalculationType = $discountCalculationType;
        $this->event = $event;
        $this->currency = $currency;
        $this->loadPrice();
        $this->recalculateNoQuery();
    }


    public function getPrice(bool $noConversion = false): ?float
    {
        if ($noConversion){
            return $this->calculatedPrice;
        }
        return $this->priceUtils->convertCurrency($this->calculatedPrice, $this->currency, $this->conversionRate);
    }

    public function getMinPrice(bool $noConversion = false): ?float
    {
        if ($noConversion){
            return $this->minPrice;
        }
        return $this->priceUtils->convertCurrency($this->minPrice, $this->currency, $this->conversionRate);
    }

    public function getVatPercentage(): ?float
    {
        return $this->vatPercentage;
    }

    public function getVatValue(): ?float
    {
        return $this->priceUtils->convertCurrency($this->vatValue, $this->currency, $this->conversionRate);
    }

    public function getDiscountPercentage(): ?float
    {
        switch ($this->discountCalculationType) {
            case DiscountCalculationType::WithDiscount:
            case DiscountCalculationType::WithDiscountPlusAfterRegistrationDiscount:
                $clientDiscount = $this->clientDiscount ?? $this->afterRegistrationBonus;
                return $this->discountCombinationStrategy->combine($this->discountPercentage ?? 0.0, $clientDiscount);
            case DiscountCalculationType::WithoutDiscount:
                return null;
            case DiscountCalculationType::OnlyProductDiscount:
                return $this->discountPercentage;
            case DiscountCalculationType::WithoutDiscountPlusAfterRegistrationDiscount:
                return $this->afterRegistrationBonus;
        }
        return $this->discountPercentage;
    }

    public function getDiscountValue(): ?float
    {
        return $this->priceUtils->convertCurrency($this->discountValue, $this->currency, $this->conversionRate);
    }

    public function getDiscountTimeUntil(): ?\DateTime
    {
        return $this->discountValidUntil;
    }

    public function getPriceValidUntil(): ?\DateTime
    {
        return $this->priceValidUntil;
    }

    public function setVatCalculationType(VatCalculationType $vatCalculationType, bool $force = false): self
    {
        $isVatExempted = false;
        if ($this->event instanceof PurchaseEvent and $this->event?->getPurchase()){
            $isVatExempted = $this->event->getPurchase()->isVatExempted();
        }
        if (!$isVatExempted or $force){
            $this->vatCalculationType = $vatCalculationType;
            $this->recalculateNoQuery();
        }

        return $this;
    }

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): self
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculateNoQuery();
        return $this;
    }

    public function setCurrency(Currency $currency, ?ConversionRate $conversionRate = null): self
    {
        $this->currency = $currency;
        if ($conversionRate){
            $this->conversionRate = $conversionRate;
        }else{
            $this->conversionRate = $this->priceUtils->getConversionRate($currency, $this->event instanceof PurchaseEvent ? $this->event->getPurchase() : null);
        }
        $this->recalculateNoQuery();
        return $this;
    }

    private function recalculateNoQuery(): void
    {
        if (!$this->price) return;

        $totalDiscountedPercentage = $this->getDiscountPercentage();

        $fullDiscountValue = $this->priceUtils->calculatePercentage($this->price, $totalDiscountedPercentage);
        $price = $this->price - $fullDiscountValue;
        if ($price < $this->minPrice) {
            $price = $this->minPrice;
        }

        switch ($this->vatCalculationType) {
            case VatCalculationType::WithoutVAT:
                break;
            case VatCalculationType::WithVAT:
                $price = $price + $this->priceUtils->calculatePercentage($price, $this->vatPercentage);
                $fullDiscountValue = $fullDiscountValue + $this->priceUtils->calculatePercentage($fullDiscountValue, $this->vatPercentage);
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($price, $this->vatPercentage);
                $fullDiscountValue = $this->priceUtils->calculatePercentage($fullDiscountValue, $this->vatPercentage);
                break;
        }

        $this->calculatedPrice = $price;
        $this->vatValue = $this->priceUtils->convertCurrency($this->priceUtils->calculatePercentage($this->calculatedPrice, $this->vatPercentage), $this->currency, $this->conversionRate);
        $this->discountValue = $fullDiscountValue;
    }


    private function loadPrice(): void
    {
        if ($this->priceEntity){
            $this->constructForPriceEntity();
            return;
        }
        if ($this->event instanceof PurchaseEvent) {
            $this->constructForPurchaseEvent();
        }
        $this->recalculatePrice();
    }

    private function constructForPriceEntity(): void
    {
        $this->minPrice = $this->priceEntity->getMinPrice();
        $this->vatPercentage = $this->priceEntity->getVat();

        if ($this->priceEntity->getDiscount()){
            $this->discountPercentage = $this->priceEntity->getDiscount();
            $this->discountValidUntil = $this->priceEntity->getValidUntil();
        }

        $this->priceValidUntil = $this->priceEntity->getValidUntil();
        $this->price = $this->priceEntity->getPrice();
    }

    private function constructForPurchaseEvent(): void
    {
        if ($this->event?->getPurchase()?->getClientDiscount()?->getDiscount()) {
            $this->clientDiscount = $this->event->getPurchase()->getClientDiscount()->getDiscount();
        }
    }

    private function recalculatePrice(): void
    {
        $date = new \DateTime("now");
        $event = $this->event;
        if ($this->event instanceof PurchaseEvent) {
            if ($this->event->getPurchase()?->getDateIssue()){
                $date = $this->event->getPurchase()->getDateIssue();
            }
            $event = $this->event->getEvent();
        }

        $priceResult = $this->priceRepository->findPriceByDateAndEvent($event, $date);

        $price = $priceResult['price'] ?? null;
        $discountedPrice = $priceResult['discounted'] ?? null;

        if (!$discountedPrice and !$price) {
            $this->emptyPrice = true;
            $this->price = null;
            return;
        }

        //to prevent if only discounted price is set
        if ($discountedPrice and !$price) {
            $price = $discountedPrice;
        }

        assert($price instanceof Price);

        $this->vatPercentage = $price->getVat();
        $this->minPrice = $price->getMinPrice();
        $this->priceValidUntil = $price->getValidUntil();

        $values = $this->calculateValues($price);
        $this->price = $values['price'];
        $this->vatValue = $values['vatAmount'];

        if ($discountedPrice) {
            assert($discountedPrice instanceof Price);
            $values = $this->calculateValues($discountedPrice);
            $this->discountValue = $values['discountedAmount'];
            $this->discountValidUntil = $discountedPrice->getValidUntil();
        }
    }

    #[ArrayShape(['price' => "int", 'discountedAmount' => "int", 'vatAmount' => "int"])]
    private function calculateValues(Price $price): array
    {
        if (!$price->getPrice()) {
            return ['price' => 0, 'discountedAmount' => 0, 'vatAmount' => 0];
        }
        $priceAmount = $price->getPrice();

        $discountedAmount = 0;
        if ($price->getDiscount()) {
            $discountedAmount = $this->priceUtils->calculatePercentage($priceAmount, $price->getDiscount());
        }

        $vatAmount = 0;
        if ($price->getVat()) {
            $vatAmount = $this->priceUtils->calculatePercentage($priceAmount, $price->getVat());
        }

        return ['price' => $priceAmount, 'discountedAmount' => $discountedAmount, 'vatAmount' => $vatAmount];
    }


}
