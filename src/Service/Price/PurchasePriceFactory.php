<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class PurchasePriceFactory
{
    public function __construct(
        private ProductVariantPriceFactory $productVariantPriceFactory,
        private CurrencyRepository         $currencyRepository,
        private PriceUtils                 $priceUtils,
        private ServiceCalculationUtils    $serviceCalculationUtils,
        private ParameterBagInterface      $parameterBag
    ) {}

    public function create(
        Purchase                $purchase,
        Currency                $currency,
        VatCalculationType      $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount,
        VoucherCalculationType  $voucherCalculationType = VoucherCalculationType::WithVoucher
    ): PurchasePrice
    {
        return new PurchasePrice(
            $purchase,
            $vatCalculationType,
            $discountCalculationType,
            $currency,
            $voucherCalculationType,
            $this->productVariantPriceFactory,
            $this->currencyRepository,
            $this->priceUtils,
            $this->serviceCalculationUtils,
            $this->parameterBag
        );
    }
}