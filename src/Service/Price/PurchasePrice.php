<?php

namespace App\Service\Price;

use App\Entity\Project\Currency;
use App\Entity\Project\PaymentType;
use App\Entity\Project\Purchase;
use App\Entity\Project\Transportation;
use App\Enum\DiscountCalculationType;
use App\Enum\VatCalculationType;
use App\Repository\Project\CurrencyRepository;

class PurchasePrice
{

    /**
     * @var ProductVariantPrice[]
     */
    private array $productVariantPrices = [];
    private ?float $purchasePrice = null;
    private ?float $transportationPrice = null;
    private ?float $paymentPrice = null;
    private Currency $defaultCurrency;

    private ?float $minPrice = null;


    public function __construct(
        private Purchase                    $purchase,
        private VatCalculationType          $vatCalculationType,
        private DiscountCalculationType     $discountCalculationType,
        private Currency                    $currency,
        private ProductVariantPriceFactory  $productVariantPriceFactory,
        private readonly CurrencyRepository $currencyRepository,
        private readonly PriceUtils         $priceUtils
    )
    {
        $this->defaultCurrency = $this->currencyRepository->findOneBy(['conversionRate' => 1]);
        $this->loadVariants();
    }

    public function getPrice(bool $includeServices = false, ?float $vat = null): ?float
    {
        $price = $this->purchasePrice;
        if ($vat) {
            $price = $this->getPriceByVat($vat);
        }
        if ($includeServices) {
            $price += $this->transportationPrice;
            $price += $this->paymentPrice;
        }
        return $price;
    }

    /**
     * @return float|null
     */
    public function getMinPrice(): ?float
    {
        return $this->minPrice;
    }

    public function getTransportationPrice(): ?float
    {
        return $this->transportationPrice;
    }

    public function getPaymentPrice(): ?float
    {
        return $this->paymentPrice;
    }

    public function setVatCalculationType(VatCalculationType $vatCalculationType): void
    {
        $this->vatCalculationType = $vatCalculationType;
        $this->recalculatePrices();
    }

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): void
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculatePrices();
    }

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
        $this->recalculatePrices();
    }


    private function loadVariants(): void
    {
        foreach ($this->purchase->getProductVariants() as $purchaseProductVariant) {
            $this->productVariantPrices [] = $this->productVariantPriceFactory->create($purchaseProductVariant, $this->currency, null, $this->vatCalculationType, $this->discountCalculationType);
        }
        $this->loadPrice();
    }

    private function recalculatePrices(): void
    {
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $productVariantPrice->setVatCalculationType($this->vatCalculationType);
            $productVariantPrice->setCurrency($this->currency);
            $productVariantPrice->setDiscountCalculationType($this->discountCalculationType);
        }
        $this->loadPrice();
    }

    private function getPriceByVat(float $vat): ?float
    {
        $price = null;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            if ($productVariantPrice->getVatPercentage() == $vat) $price += $productVariantPrice->getPrice();
        }
        return $price;
    }

    private function loadPrice(): void
    {
        $price = null;
        $minPrice = null;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $price += $productVariantPrice->getPrice();
            $minPrice += $productVariantPrice->getMinPrice();
        }
        $this->purchasePrice = $price;
        $this->minPrice = $minPrice;
        $this->loadServicePrices();
    }

    private function loadServicePrices(): void
    {
        $purchasePrice = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $clonedProductVariantPrice = clone $productVariantPrice;
            $clonedProductVariantPrice->setCurrency($this->defaultCurrency);
            $clonedProductVariantPrice->setVatCalculationType(VatCalculationType::WithoutVAT);
            $purchasePrice += $clonedProductVariantPrice->getPrice();
        }

        if ($this->purchase->getTransportation()) {
            $this->setTransportationPrice($purchasePrice, $this->purchase->getTransportation());
        }

        if ($this->purchase->getPaymentType()) {
            $this->setPaymentPrice($purchasePrice, $this->purchase->getPaymentType());
        }
    }

    private function setPaymentPrice(float $purchasePrice, PaymentType $paymentType): void
    {

        if ($purchasePrice >= $paymentType->getFreeFromPrice() or $paymentType->getPrice() < 1) {
            $this->paymentPrice = 0;
            return;
        }

        switch ($this->vatCalculationType){
            case VatCalculationType::WithoutVAT:
                $price = $paymentType->getPrice();
                break;
            case VatCalculationType::WithVAT:
                $vatValue = $this->priceUtils->calculatePercentage($paymentType->getPrice(), $paymentType->getVat());
                $price = $paymentType->getPrice()+$vatValue;
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($paymentType->getPrice(), $paymentType->getVat());
                break;
            default:
                throw new \Exception('Unknown Enum:VatCalculationType case');
        }

        $this->paymentPrice = $this->priceUtils->convertCurrency($price, $this->currency);

    }

    private function setTransportationPrice(float $purchasePrice, Transportation $transportation): void
    {
        if ($purchasePrice >= $transportation->getFreeFromPrice() or $transportation->getPrice() < 1) {
            $this->transportationPrice = 0;
            return;
        }

        switch ($this->vatCalculationType){
            case VatCalculationType::WithoutVAT:
                $price = $transportation->getPrice();
                break;
            case VatCalculationType::WithVAT:
                $vatValue = $this->priceUtils->calculatePercentage($transportation->getPrice(), $transportation->getVat());
                $price = $transportation->getPrice()+$vatValue;
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($transportation->getPrice(), $transportation->getVat());
                break;
            default:
                throw new \Exception('Unknown Enum:VatCalculationType case');
        }

        $this->transportationPrice = $this->priceUtils->convertCurrency($price, $this->currency);
    }


}