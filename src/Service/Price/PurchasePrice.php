<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
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

    private float $vouchersValue = 0;
    private array $vouchersUsed = [];

    private float $discountValue = 0;
    private float $discountPercentage = 0;

    private ?float $minPrice = null;


    public function __construct(
        private Purchase                            $purchase,
        private VatCalculationType                  $vatCalculationType,
        private DiscountCalculationType             $discountCalculationType,
        private Currency                            $currency,
        private VoucherCalculationType              $voucherCalculationType,
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly CurrencyRepository         $currencyRepository,
        private readonly HandlingPriceRepository    $handlingPriceRepository,
        private readonly PriceUtils                 $priceUtils
    )
    {
        foreach ($this->purchase->getVouchersUsed() as $voucher){
            $this->vouchersUsed[] = $voucher;
        }
        $this->defaultCurrency = $this->currencyRepository->findOneBy(['conversionRate' => 1]);
        $this->loadVariants();
        $this->calculateVouchersValue();
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
        $price = $this->applyVoucher($price);
        return $this->priceUtils->convertCurrency($price, $this->currency);
    }

    /**
     * @return float|null
     */
    public function getMinPrice(): ?float
    {
        return $this->priceUtils->convertCurrency($this->minPrice, $this->currency);
    }

    public function getTransportationPrice(): ?float
    {
        if (!$this->transportationPrice) {
            return null;
        }
        return $this->priceUtils->convertCurrency($this->transportationPrice, $this->currency);
    }

    public function getPaymentPrice(): ?float
    {
        if (!$this->paymentPrice) {
            return null;
        }
        return $this->priceUtils->convertCurrency($this->paymentPrice, $this->currency);
    }

    /**
     * @return Voucher[]
     */
    public function getVouchersUsed(): array
    {
        return $this->vouchersUsed;
    }

    public function getVouchersUsedValue(): float
    {
        if ($this->vouchersValue > 0){
            return $this->priceUtils->convertCurrency($this->vouchersValue, $this->currency);
        }
        return 0;
    }

    public function getDiscountValue() : float
    {
        return $this->discountValue;
    }

    public function getDiscountPercentage() : float
    {
        return $this->discountPercentage;
    }

    public function addVoucher(Voucher $voucher): self
    {
        $this->vouchersUsed[] = $voucher;
        $this->calculateVouchersValue();
        return $this;
    }
    public function setVoucherCalculationType(VoucherCalculationType $voucherCalculationType): self
    {
        $this->voucherCalculationType = $voucherCalculationType;
        return $this;
    }

    public function setVatCalculationType(VatCalculationType $vatCalculationType): PurchasePrice
    {
        $this->vatCalculationType = $vatCalculationType;
        $this->recalculatePrices();
        return $this;
    }

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): PurchasePrice
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculatePrices();
        return $this;
    }

    public function setCurrency(Currency $currency): PurchasePrice
    {
        $this->currency = $currency;
        $this->recalculatePrices();
        return $this;
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

            //basically $productVariantPrice->getVatPercentage() == $vat but with a tolerance for float (edge-case where one value can be 21.0 and the other 20.9999... because of how computers handle floating points)
            if (abs((float)$productVariantPrice->getVatPercentage() - $vat) < 0.001) {
                $price += $productVariantPrice->getPrice(true);
            }
        }
        return $this->priceUtils->convertCurrency($price, $this->currency);
//        return $price;
    }

    private function loadPrice(): void
    {
        $price = null;
        $minPrice = null;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $price += $productVariantPrice->getPrice(true);
            $minPrice += $productVariantPrice->getMinPrice(true);
        }
        $this->purchasePrice = $price;
        $this->minPrice = $minPrice;
        $this->loadServicePrices();
        $this->loadDiscountValue();
        $this->loadDiscountPercetage();
    }

    private function loadServicePrices(): void
    {
        $purchasePrice = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $clonedProductVariantPrice = clone $productVariantPrice;
            $clonedProductVariantPrice->setCurrency($this->defaultCurrency);
            $clonedProductVariantPrice->setVatCalculationType(VatCalculationType::WithoutVAT);
            $purchasePrice += $clonedProductVariantPrice->getPrice(true);
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

        if (!$handlingPrice or $purchasePrice >= $handlingPrice->getFreeFromPrice() or $handlingPrice->getPrice() < 1) {
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

        $this->paymentPrice = $price;

    }

    private function setTransportationPrice(float $purchasePrice, Transportation $transportation): void
    {
        // TODO: use ServiceCalculationUtils::calculateServicePrice() to retrieve transportation price

        $handlingPrice = $this->handlingPriceRepository->GetByDate($transportation);

        if (!$handlingPrice or $purchasePrice >= $handlingPrice->getFreeFromPrice() or $handlingPrice->getPrice() < 1) {
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

        $this->transportationPrice = $price;
    }

    private function applyVoucher(?float $price):?float
    {
        if ($this->vouchersValue === 0 or $this->voucherCalculationType === VoucherCalculationType::WithoutVoucher){
            return $price;
        }
        $priceAppliedVoucher = $price - $this->vouchersValue;
        if ($this->voucherCalculationType === VoucherCalculationType::WithVoucherToMinus){
            return $priceAppliedVoucher;
        }
        return max($priceAppliedVoucher, 0);
    }

    private function calculateVouchersValue() : void
    {
        $finalVouchersValue = 0;
        foreach ($this->vouchersUsed as $voucher){
            assert($voucher instanceof Voucher);
            $finalVouchersValue += $voucher->getAmount();
        }
        $this->vouchersValue = $finalVouchersValue;
    }

    private function loadDiscountValue() : void
    {
        $variantDiscountSum = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $variantDiscountSum += $productVariantPrice->getDiscountValue();
        }
        $this->discountValue = $variantDiscountSum;
    }

    private function loadDiscountPercetage() : void
    {
        $discountPercentageSum = 0;
        $discountPercentageCount = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $discountPercentageSum += $productVariantPrice->getDiscountPercentage();
            $discountPercentageCount++;
        }        

        $this->discountPercentage = $discountPercentageCount  > 0 ? $discountPercentageSum/$discountPercentageCount : 0;
    }

}