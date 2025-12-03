<?php

namespace Greendot\EshopBundle\Service\Price;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;

readonly class CalculatedPricesService
{
    public function __construct(
        private ProductVariantPriceFactory $productVariantPriceFactory,
        private CurrencyManager            $currencyManager,
        private EntityManagerInterface     $entityManager,
    ) {}

    public function makeCalculatedPricesForProductVariant(ProductVariant $variant, $date = new \DateTime()) : ProductVariant
    {
        if (!empty($variant->getCalculatedPrices())){
            return $variant;
        }

        
        // get unique minimalAmounts from productVariant.prices
        $uniqueMinimalAmounts = []; 
        $variantPrices = $this->entityManager->getRepository(Price::class)->findBy(['productVariant'=>$variant->getId()]);
        foreach($variantPrices as $price){
            // check valid
            if ($price->getValidFrom() > $date || ($price->getValidUntil() !== null && $date > $price->getValidUntil())) {
                continue;
            }
            
            // if not in array add
            if(!in_array($price->getMinimalAmount(), $uniqueMinimalAmounts)) {
                $uniqueMinimalAmounts[] = $price->getMinimalAmount();
            }
        }
        sort($uniqueMinimalAmounts);
        
        
        // for each minimalAmount, make calculated prices object and add them to list
        $variantPrice = $this->productVariantPriceFactory->create($variant, $this->currencyManager->get()); // price calculator object
        $calculatedPricesList = [];
        foreach($uniqueMinimalAmounts as $minimalAmount){
            $variantPrice->setAmount($minimalAmount);
            $calculatedPricesObject = [];

            $variantPrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
            $variantPrice->setVatCalculationType(VatCalculationType::WithVAT);
            $calculatedPricesObject['priceVat'] = $variantPrice->getPiecePrice();

            $variantPrice->setVatCalculationType(vatCalculationType::WithoutVAT);
            $calculatedPricesObject['priceNoVat'] = $variantPrice->getPiecePrice();

            $variantPrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
            $calculatedPricesObject['priceNoVatNoDiscount'] = $variantPrice->getPiecePrice();

            $variantPrice->setVatCalculationType(VatCalculationType::WithVAT);
            $calculatedPricesObject['priceVatNoDiscount'] = $variantPrice->getPiecePrice();

            $calculatedPricesList[$minimalAmount] = $calculatedPricesObject;
        }

        // set variant calculated prices to be the list
        $variant->setCalculatedPrices($calculatedPricesList);
        return $variant;
    }

    public function makeCalculatedPricesForProduct(Product $product) : Product
    {
        if (!empty($product->getCalculatedPrices())){
            return $product;
        }


        // get the lowest price from among product.productVariants and set the calculatedPrices object to product
        $minimalPrice = 0;
        foreach ($product->getProductVariants() as $variant) {
            $this->makeCalculatedPricesForProductVariant($variant);

            $variantCalculatedPrices = $variant->getCalculatedPrices();

            // debug
            if (empty($variantCalculatedPrices))
            {
                $product->setCalculatedPrices([]);
                return $product;
//                dump($variant);
//                dd($product);
            }

            // get the 1st (with the lowest minimalAmount) calculated prices object from variant
            $variantCalculatedPricesMin = $variantCalculatedPrices[array_key_first($variantCalculatedPrices)];

            if ($minimalPrice === 0 || $minimalPrice > $variantCalculatedPricesMin['priceNoVat'])
            {
                $minimalPrice = $variantCalculatedPricesMin['priceNoVat'];
                $product->setCalculatedPrices($variantCalculatedPricesMin);
            }
        }
        return $product;
    }
}