<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;

readonly class PurchasePriceFactory
{
    public function __construct(
        private ProductVariantPriceFactory $productVariantPriceFactory,
        private PriceUtils                 $priceUtils,
        private ServiceCalculationUtils    $serviceCalculationUtils,
        private SettingsRepository         $settingsRepository
    ) {}

    public function create(
        Purchase                $purchase,
        Currency                $currency,
        VatCalculationType      $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount,
        VoucherCalculationType  $voucherCalculationType = VoucherCalculationType::WithVoucher,
    ): PurchasePrice
    {
        // FIXME: Rework vat-exempted logic
        if ($vatCalculationType === VatCalculationType::WithVAT && $purchase->isVatExempted()) {
            $vatCalculationType = VatCalculationType::WithoutVAT;
        }
        //

        $conversionRate = $this->priceUtils->getConversionRate($currency, $purchase);

        return new PurchasePrice(
            $purchase,
            $vatCalculationType,
            $discountCalculationType,
            $currency,
            $conversionRate,
            $voucherCalculationType,
            $this->productVariantPriceFactory,
            $this->priceUtils,
            $this->serviceCalculationUtils,
            $this->settingsRepository,
        );
    }
}