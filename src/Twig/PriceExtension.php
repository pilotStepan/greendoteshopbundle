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

        // PurchaseProductVariant pricing (order-specific, possibly custom prices) goes through
        // create() unchanged — the prefetch shape below only covers plain ProductVariant pricing.
        if ($productVariant instanceof ProductVariant) {
            $allPrices = $this->priceRepository->findPricesByDateAndProductVariantNew($productVariant, new \DateTime(), null);

            return $this->productVariantPriceFactory->createFromPrefetchedPrices(
                pv: $productVariant,
                prefetchedPrices: $allPrices,
                currencyOrConversionRate: $currency,
                amount: $amount,
                vatCalculationType: $vatCalculationType,
                discountCalculationType: $discountCalculationType,
                parentProduct: $parentProduct,
            );
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
        // One fetch of every valid price row for this variant (grouped by minimalAmount, DESC —
        // same shape findPricesByDateAndProductVariantNew() always returns), reused for every
        // tier below instead of each tier re-querying getMinimalAmount() +
        // findPricesByDateAndProductVariantNew() from scratch.
        $allPrices = $this->priceRepository->findPricesByDateAndProductVariantNew($productVariant, new \DateTime(), null);

        $array = [];
        foreach (array_keys($allPrices) as $minimalAmount) {
            // ProductVariantPrice does its own ceiling filtering against the full map — see its
            // $prefetchedPrices constructor doc.
            $array[$minimalAmount] = $this->productVariantPriceFactory->createFromPrefetchedPrices(
                pv: $productVariant,
                prefetchedPrices: $allPrices,
                currencyOrConversionRate: $currency,
                amount: $minimalAmount,
                vatCalculationType: $vatCalculationType,
                discountCalculationType: $discountCalculationType,
            );
        }
        return $array;
    }
}