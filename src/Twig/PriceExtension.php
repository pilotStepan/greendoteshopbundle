<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Utils\PriceHelper;
use Twig\Attribute\AsTwigFunction;

class PriceExtension
{
    public function __construct(
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly CurrencyManager            $currencyManager,
        private readonly PriceRepository            $priceRepository,
        private readonly CurrencyRepository         $currencyRepository,
    ) {}

    #[AsTwigFunction('format_money')]
    public function formatMoney(Money $money, bool $showFree = false): string
    {
        $currency = $this->currencyRepository->findOneByIso($money->getIso());
        if (!$currency) {
            throw new \RuntimeException(sprintf('No Currency found for ISO code "%s".', $money->getIso()));
        }

        return PriceHelper::formatPrice($money->getValue(), $currency, $showFree);
    }

    #[AsTwigFunction('money_zero')]
    public function moneyZero(Currency $currency): Money
    {
        return Money::zero($currency);
    }

    #[AsTwigFunction('money_currencies_json')]
    public function currenciesJson(): string
    {
        $currencies = [];
        foreach ($this->currencyRepository->findAll() as $currency) {
            $currencies[$currency->getIso()] = [
                'symbol' => $currency->getSymbol(),
                'rounding' => $currency->getRounding() ?? 0,
                'isSymbolLeft' => (bool) $currency->isSymbolLeft(),
            ];
        }

        return json_encode($currencies, JSON_THROW_ON_ERROR);
    }

    #[AsTwigFunction('create_product_variant_price')]
    public function createProductVariantPrice(
        ProductVariant|PurchaseProductVariant $productVariant,
        ?Currency                             $currency = null,
        VatCalculationType                    $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType               $discountCalculationType = DiscountCalculationType::WithDiscount,
        ?int                                  $amount = null,
        Product|ProductProduct|null           $parentProduct = null
    ): ProductVariantPrice
    {
        if (!$currency) {
            $currency = $this->currencyManager->get();
        }

        return $this->productVariantPriceFactory->create(
            pv: $productVariant,
            currencyOrConversionRate: $currency,
            amount: $amount,
            vatCalculationType: $vatCalculationType,
            discountCalculationType: $discountCalculationType,
            parentProduct: $parentProduct
        );
    }

    /**
     * @param ProductVariant $productVariant
     * @param Currency $currency
     * @return ProductVariantPrice[]
     */
    #[AsTwigFunction('price_table_array')]
    public function priceTableArray(
        ProductVariant $productVariant,
        Currency       $currency,
        VatCalculationType $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount
    ): array
    {
        $minimalAmounts = $this->priceRepository->getUniqueMinimalAmounts($productVariant);

        $array = [];
        foreach ($minimalAmounts as $minimalAmount) {
            $array[$minimalAmount] = $this->productVariantPriceFactory->create(
                pv: $productVariant,
                currencyOrConversionRate: $currency,
                amount: $minimalAmount,
                vatCalculationType: $vatCalculationType,
                discountCalculationType: $discountCalculationType,
            );
        }
        return $array;
    }
}