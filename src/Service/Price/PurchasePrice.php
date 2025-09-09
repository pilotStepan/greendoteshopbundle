<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Settings;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;

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

    private bool $freeFromPriceIncludesVat = false;


    public function __construct(
        private Purchase                            $purchase,
        private VatCalculationType                  $vatCalculationType,
        private DiscountCalculationType             $discountCalculationType,
        private Currency                            $currency,
        private VoucherCalculationType              $voucherCalculationType,
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly CurrencyRepository         $currencyRepository,
        private readonly PriceUtils                 $priceUtils,
        private readonly ServiceCalculationUtils    $serviceCalculationUtils,
        SettingsRepository         $settingsRepository
    )
    {
        foreach ($this->purchase->getVouchersUsed() as $voucher) {
            $this->vouchersUsed[] = $voucher;
        }
        $this->defaultCurrency = $this->currencyRepository->findOneBy(['conversionRate' => 1]);
        $doesFreeFromPriceIncludesVat = $settingsRepository->findOneBy(['value' => 'free_from_price_includes_vat']);
        if ($doesFreeFromPriceIncludesVat instanceof Settings){
            $doesFreeFromPriceIncludesVat = filter_var($doesFreeFromPriceIncludesVat->getValue(), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }else{
            $doesFreeFromPriceIncludesVat = false;
        }

        if (is_bool($doesFreeFromPriceIncludesVat) && $doesFreeFromPriceIncludesVat){
            $this->freeFromPriceIncludesVat = true;
        }
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
        if ($this->vouchersValue > 0) {
            return $this->priceUtils->convertCurrency($this->vouchersValue, $this->currency);
        }
        return 0;
    }

    public function getDiscountValue(): float
    {
        return $this->discountValue;
    }

    public function getDiscountPercentage(): float
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

    public function setDiscountCalculationType(DiscountCalculationType $discountCalculationType): self
    {
        $this->discountCalculationType = $discountCalculationType;
        $this->recalculatePrices();
        return $this;
    }

    public function setCurrency(Currency $currency): self
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
        $this->loadDiscountPercentage();
    }

    private function loadServicePrices(): void
    {
        $purchasePrice = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $clonedProductVariantPrice = clone $productVariantPrice;
            $clonedProductVariantPrice->setCurrency($this->defaultCurrency);
            if ($this->freeFromPriceIncludesVat){
                $clonedProductVariantPrice->setVatCalculationType(VatCalculationType::WithVAT);
            }else{
                $clonedProductVariantPrice->setVatCalculationType(VatCalculationType::WithoutVAT);
            }
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
        $this->paymentPrice = $this->serviceCalculationUtils->calculateServicePrice($paymentType, $this->defaultCurrency, $this->vatCalculationType, $purchasePrice, true);
    }

    private function setTransportationPrice(float $purchasePrice, Transportation $transportation): void
    {
        $this->transportationPrice = $this->serviceCalculationUtils->calculateServicePrice($transportation,$this->defaultCurrency, $this->vatCalculationType, $purchasePrice, true);
    }

    private function applyVoucher(?float $price): ?float
    {
        if ($this->vouchersValue === 0 or $this->voucherCalculationType === VoucherCalculationType::WithoutVoucher) {
            return $price;
        }
        $priceAppliedVoucher = $price - $this->vouchersValue;
        if ($this->voucherCalculationType === VoucherCalculationType::WithVoucherToMinus) {
            return $priceAppliedVoucher;
        }
        return max($priceAppliedVoucher, 0);
    }

    private function calculateVouchersValue(): void
    {
        $finalVouchersValue = 0;
        foreach ($this->vouchersUsed as $voucher) {
            assert($voucher instanceof Voucher);
            $finalVouchersValue += $voucher->getAmount();
        }
        $this->vouchersValue = $finalVouchersValue;
    }

    private function loadDiscountValue(): void
    {
        $variantDiscountSum = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $variantDiscountSum += $productVariantPrice->getDiscountValue();
        }
        $this->discountValue = $variantDiscountSum;
    }

    private function loadDiscountPercentage(): void
    {
        $discountPercentageSum = 0;
        $discountPercentageCount = 0;
        foreach ($this->productVariantPrices as $productVariantPrice) {
            $discountPercentageSum += $productVariantPrice->getDiscountPercentage();
            $discountPercentageCount++;
        }

        $this->discountPercentage = $discountPercentageCount > 0 ? $discountPercentageSum / $discountPercentageCount : 0;
    }

}