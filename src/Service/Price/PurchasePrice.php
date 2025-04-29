<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;

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
        private Purchase                            $purchase,
        private VatCalculationType                  $vatCalculationType,
        private DiscountCalculationType             $discountCalculationType,
        private Currency                            $currency,
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly CurrencyRepository         $currencyRepository,
        private readonly HandlingPriceRepository    $handlingPriceRepository,
        private readonly PriceUtils                 $priceUtils
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
        $handlingPrice = $this->handlingPriceRepository->GetByDate($paymentType);

        if ($purchasePrice >= $handlingPrice->getFreeFromPrice() or $handlingPrice->getPrice() < 1) {
            $this->paymentPrice = 0;
            return;
        }

        switch ($this->vatCalculationType) {
            case VatCalculationType::WithoutVAT:
                $price = $handlingPrice->getPrice();
                break;
            case VatCalculationType::WithVAT:
                $vatValue = $this->priceUtils->calculatePercentage($handlingPrice->getPrice(), $handlingPrice->getVat());
                $price = $handlingPrice->getPrice() + $vatValue;
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($handlingPrice->getPrice(), $handlingPrice->getVat());
                break;
            default:
                throw new \Exception('Unknown Enum:VatCalculationType case');
        }

        $this->paymentPrice = $this->priceUtils->convertCurrency($price, $this->currency);

    }

    private function setTransportationPrice(float $purchasePrice, Transportation $transportation): void
    {
        $handlingPrice = $this->handlingPriceRepository->GetByDate($transportation);

        if ($purchasePrice >= $handlingPrice->getFreeFromPrice() or $handlingPrice->getPrice() < 1) {
            $this->transportationPrice = 0;
            return;
        }

        switch ($this->vatCalculationType) {
            case VatCalculationType::WithoutVAT:
                $price = $handlingPrice->getPrice();
                break;
            case VatCalculationType::WithVAT:
                $vatValue = $this->priceUtils->calculatePercentage($handlingPrice->getPrice(), $handlingPrice->getVat());
                $price = $handlingPrice->getPrice() + $vatValue;
                break;
            case VatCalculationType::OnlyVAT:
                $price = $this->priceUtils->calculatePercentage($handlingPrice->getPrice(), $handlingPrice->getVat());
                break;
            default:
                throw new \Exception('Unknown Enum:VatCalculationType case');
        }

        $this->transportationPrice = $this->priceUtils->convertCurrency($price, $this->currency);
    }


}