<?php

namespace Greendot\EshopBundle\Service\Price;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Dto\calculatedPrices\PurchaseCalculatedPricesMatrix;
use Greendot\EshopBundle\Dto\calculatedPrices\VariantCalculatedPricesMatrix;
use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use SebastianBergmann\RecursionContext\Context;

//DO NOT MAKE FINAL!!!!
class CalculatedPricesService
{
    public function __construct(
        private readonly ProductVariantPriceFactory  $productVariantPriceFactory,
        private readonly PurchasePriceFactory        $purchasePriceFactory,
        private readonly CurrencyManager             $currencyManager,
        private readonly PriceRepository             $priceRepository,
    ) {}    

    /**
     * Sets the calculated prices collection for variant for all unique minimal amounts
     */
    public function makeCalculatedPricesForProductVariant(
        ProductVariant              $variant, 
        ?ProductVariantPriceContext  $context = null,
    ) : ProductVariant
    {
        if (!empty($variant->getCalculatedPrices())){
            return $variant;
        }        

        $context = $this->resolveVariantContext($context);

        $productVariantPrice = $this->productVariantPriceFactory->createFromContext(  
            pv: $variant, 
            context: $context
        );
        $amounts = $this->priceRepository->getUniqueMinimalAmounts($variant);

        $calculatedPricesCollection = $this->createVariantCalculatedPricesCollection($productVariantPrice, $amounts);
        
        $variant->setCalculatedPrices($calculatedPricesCollection);
        return $variant;
    }


    /**
     * Sets the calculated prices matrix for product from the cheapest variant.
     */
    public function makeCalculatedPricesForProduct(
        Product $product,
        ?ProductVariantPriceContext $context = null
    ) : Product
    {
        if (!empty($product->getCalculatedPrices())){
            return $product;
        }
        $context = $this->resolveVariantContext($context);

        $productVariantPrice = $this->findCheapestVariantPriceForProduct($product, $context);
        $calculatedPricesMatrix = $this->createVariantCalculatedPricesMatrix($productVariantPrice);

        $product->setCalculatedPrices((array)$calculatedPricesMatrix);
        return $product;
    }

    /** 
     * Calls makeCalculatedPrices functions on product and its variants.
     */
    public function makeCalculatedPricesForProductWithVariants(
        Product                     $product, 
        ?ProductVariantPriceContext  $context = null,
    ) : Product
    {
        $context = $this->resolveVariantContext($context);

        $this->makeCalculatedPricesForProduct($product, $context);
        foreach($product->getProductVariants() as $variant){
            $this->makeCalculatedPricesForProductVariant($variant, $context);
        }

        return $product;
    }


    public function makeCalculatedPricesForPurchase(Purchase $purchase) : Purchase
    {
        if (!empty($purchase->getCalculatedPrices())) {
            return $purchase;
        }

        // Make calculated prices for purchase
        $purchasePrice = $this->purchasePriceFactory->create($purchase, $this->currencyManager->get());

        $calculatedPricesMatrix = $this->createPurchaseCalculatedPricesMatrix($purchasePrice);
      
        $purchase->setCalculatedPrices((array)$calculatedPricesMatrix);

        return $purchase;
    }

    public function makeCalculatedPricesForPurchaseProductVariant(
        PurchaseProductVariant      $purchaseProductVariant, 
        ?ProductVariantPriceContext $context = null
    ) : PurchaseProductVariant
    {
        if (!empty($purchaseProductVariant->getCalculatedPrices())){
            return $purchaseProductVariant;
        }
        $context = $this->resolveVariantContext($context);

        $variantPrice = $this->productVariantPriceFactory->createFromContext($purchaseProductVariant, $context);
        $calculatedPricesMatrix = $this->createVariantCalculatedPricesMatrix($variantPrice);
        
        $purchaseProductVariant->setCalculatedPrices((array)$calculatedPricesMatrix);
        return $purchaseProductVariant;
    }

    public function makeCalculatedPricesForPurchaseWithVariants(
        Purchase                    $purchase, 
        ?ProductVariantPriceContext $context = null
    ) : Purchase
    {
        $context = $this->resolveVariantContext($context);

        $this->makeCalculatedPricesForPurchase($purchase);
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $this->makeCalculatedPricesForPurchaseProductVariant($purchaseProductVariant, $context);
        }

        return $purchase;
    }

    protected function createVariantCalculatedPricesMatrix(ProductVariantPrice $productVariantPrice)
    {
        $priceVat = $productVariantPrice
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
            ->getPiecePrice();

        $priceNoVat = $productVariantPrice
            ->setVatCalculationType(VatCalculationType::WithoutVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithDiscount)
            ->getPiecePrice();

         $priceVatNoDiscount = $productVariantPrice
            ->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->getPiecePrice();

        $priceNoVatNoDiscount = $productVariantPrice
            ->setVatCalculationType(VatCalculationType::WithoutVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount)
            ->getPiecePrice();

        return new VariantCalculatedPricesMatrix(
            priceVat: $priceVat,
            priceNoVat: $priceNoVat,
            priceVatNoDiscount: $priceVatNoDiscount,
            priceNoVatNoDiscount: $priceNoVatNoDiscount,

        );
    }

    protected function createPurchaseCalculatedPricesMatrix(
        PurchasePrice $purchasePrice
    ) : PurchaseCalculatedPricesMatrix
    {
        $purchasePrice->setVatCalculationType(VatCalculationType::WithVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $priceVat = $purchasePrice->getPrice(true);
        $priceVatNoServices = $purchasePrice->getPrice(false);

        $purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $priceNoVat = $purchasePrice->getPrice(true);
        $priceNoVatNoServices = $purchasePrice->getPrice(false);


        $purchasePrice->setVatCalculationType(VatCalculationType::WithVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);        
        $priceVatNoDiscount = $purchasePrice->getPrice(true);
        $priceVatNoDiscountNoServices = $purchasePrice->getPrice(false);

        $purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);        
        $priceNoVatNoDiscount = $purchasePrice->getPrice(true);
        $priceNoVatNoDiscountNoServices = $purchasePrice->getPrice(false);

        return new PurchaseCalculatedPricesMatrix(
            priceVat:                       $priceVat,
            priceNoVat:                     $priceNoVat,
            priceVatNoDiscount:             $priceVatNoDiscount,
            priceNoVatNoDiscount:           $priceNoVatNoDiscount,
            priceVatNoServices:             $priceVatNoServices,
            priceNoVatNoServices:           $priceNoVatNoServices,
            priceVatNoDiscountNoServices:   $priceVatNoDiscountNoServices,
            priceNoVatNoDiscountNoServices: $priceNoVatNoDiscountNoServices
        );
    }

    protected function createVariantCalculatedPricesCollection(ProductVariantPrice $productVariantPrice, array $amounts) : array 
    {
        $calculatedPricesCollection = [];

        foreach($amounts as $minimalAmount){
            $productVariantPrice->setAmount($minimalAmount);
            $calculatedPricesMatrix = $this->createVariantCalculatedPricesMatrix($productVariantPrice);
            $calculatedPricesCollection[$minimalAmount] = (array)$calculatedPricesMatrix;
        }
        return $calculatedPricesCollection;
    }

    protected function resolveVariantContext(?ProductVariantPriceContext $context) : ProductVariantPriceContext
    {
        return $context ?? new ProductVariantPriceContext( 
            currencyOrConversionRate: $this->currencyManager->get()
        );
    }

    protected function findCheapestVariantPriceForProduct(
        Product $product, 
        ?ProductVariantPriceContext $context = null
    )  : ProductVariantPrice
    {
        $context = $this->resolveVariantContext($context);

        // $cheapestVariantPrice = null;
        // $currentPiecePrice = null;
        // foreach ($product->getProductVariants() as $variant) {
       
        //     $productVariantPrice = $this->productVariantPriceFactory->createFromContext(  
        //         pv: $variant, 
        //         context: $context
        //     );

        //     $piecePrice = $productVariantPrice->getPiecePrice();

        //     if (!$currentPiecePrice || $piecePrice < $currentPiecePrice)
        //     {
        //         $currentPiecePrice = $piecePrice;
        //         $cheapestVariantPrice = $productVariantPrice;
        //     }
        // }
        $cheapestPrice = $this->priceRepository->findCheapestPriceForProduct($product);
        $cheapestVariantPrice = $this->productVariantPriceFactory->entityLoadFromContext($cheapestPrice, $context);
        return $cheapestVariantPrice;
    }  
}