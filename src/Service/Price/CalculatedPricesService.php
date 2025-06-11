<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\SessionService;

class CalculatedPricesService
{

    public function __construct(
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly SessionService             $sessionService
    )
    {
    }
    public function makeCalculatedPricesForProductVariant(ProductVariant $variant) : ProductVariant
    {
        if (!empty($variant->getCalculatedPrices())){
            return $variant;
        }


        $currency = $this->sessionService->getCurrency();
        $variantPrice = $this->productVariantPriceFactory->create($variant, $currency);
        $now = new \DateTime();

        $variantMinimalAmount = 0;
        foreach ($variant->getPrice() as $price) {
            if ($price->getValidFrom() > $now || ($price->getValidUntil() !== null && $now > $price->getValidUntil())) {
                continue;
            }

            $priceMinimalAmount = $price->getMinimalAmount();
            $variantPrice->setAmount($priceMinimalAmount);
            $calculatedPrices = [];

            $variantPrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
            $variantPrice->setVatCalculationType(VatCalculationType::WithVAT);
            $calculatedPrices['priceVat'] = $variantPrice->getPiecePrice();

            $variantPrice->setVatCalculationType(vatCalculationType::WithoutVAT);
            $calculatedPrices['priceNoVat'] = $variantPrice->getPiecePrice();

            $variantPrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
            $calculatedPrices['priceNoVatNoDiscount'] = $variantPrice->getPiecePrice();

            $variantPrice->setVatCalculationType(VatCalculationType::WithVAT);
            $calculatedPrices['priceVatNoDiscount'] = $variantPrice->getPiecePrice();

            $price->setCalculatedPrices($calculatedPrices);

            if ( $variantMinimalAmount === 0 || $variantMinimalAmount > $priceMinimalAmount) {
                $variantMinimalAmount = $priceMinimalAmount;
                $variant->setCalculatedPrices($calculatedPrices);
            }
        }
        return $variant;
    }

    public function makeCalculatedPricesForProduct(Product $product) : Product
    {
        if (!empty($product->getCalculatedPrices())){
            return $product;
        }


        $minimalPrice = 0;
        foreach ($product->getProductVariants() as $variant) {
            $this->makeCalculatedPricesForProductVariant($variant);

            $variantCalculatedPrices = $variant->getCalculatedPrices();

            // debug
            if (empty($variantCalculatedPrices))
            {
                dd($product);
            }

            if ($minimalPrice === 0 || $minimalPrice > $variantCalculatedPrices['priceNoVat'])
            {
                $minimalPrice = $variantCalculatedPrices['priceNoVat'];
                $product->setCalculatedPrices($variantCalculatedPrices);
            }
        }
        return $product;
    }
}