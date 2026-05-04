<?php

namespace Greendot\EshopBundle\Service\Price;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Dto\calculatedPrices\AdditionalPurchaseCostMatrix;
use Greendot\EshopBundle\Dto\calculatedPrices\PurchaseCalculatedPricesMatrix;
use Greendot\EshopBundle\Dto\calculatedPrices\ServiceCalculatedPrices;
use Greendot\EshopBundle\Dto\calculatedPrices\VariantCalculatedPricesMatrix;
use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;

//DO NOT MAKE FINAL!!!!
class CalculatedPricesService
{
    public function __construct(
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly PurchasePriceFactory       $purchasePriceFactory,
        private readonly CurrencyManager            $currencyManager,
        private readonly PriceRepository            $priceRepository,
        private readonly ServiceCalculationUtils    $serviceCalculationUtils,
        private readonly EntityManagerInterface     $entityManager
    )
    {
    }

    /**
     * Sets the calculated prices collection for variant for all unique minimal amounts
     */
    public function makeCalculatedPricesForProductVariant(
        ProductVariant              $variant,
        ?ProductVariantPriceContext $context = null,
    ): ProductVariant
    {
        if (!empty($variant->getCalculatedPrices())) {
            return $variant;
        }

        $context = $this->resolveVariantContext($context);

        $productVariantPrice = $this->productVariantPriceFactory->createFromContext(
            pv: $variant,
            context: $context
        );

        if (!$productVariantPrice) {
            return $variant;
        }

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
        ?ProductVariantPriceContext $context = null,
        ?Price $cheapestPrice = null,
    ) : Product
    {
        if (!empty($product->getCalculatedPrices())){
            return $product;
        }
        $context = $this->resolveVariantContext($context);

        if (!$cheapestPrice) {
            $cheapestPrice = $this->priceRepository->findCheapestPriceForProduct($product);
        }

        if (!$cheapestPrice) {
            return $product;
        }

        $productVariantPrice = $this->productVariantPriceFactory->entityLoadFromContext($cheapestPrice, $context);

        if (!$productVariantPrice)
        {
            return $product;
        }

        $calculatedPricesMatrix = $this->createVariantCalculatedPricesMatrix($productVariantPrice);

        $product->setCalculatedPrices((array)$calculatedPricesMatrix);
        return $product;
    }

    /**
     * Calls makeCalculatedPrices functions on product and its variants.
     */
    public function makeCalculatedPricesForProductWithVariants(
        Product                     $product,
        ?ProductVariantPriceContext $context = null,
    ): Product
    {
        $context = $this->resolveVariantContext($context);

        $this->makeCalculatedPricesForProduct($product, $context);
        foreach ($product->getProductVariants() as $variant) {
            $this->makeCalculatedPricesForProductVariant($variant, $context);
        }

        return $product;
    }


    public function makeCalculatedPricesForPurchase(Purchase $purchase): Purchase
    {
        if (!empty($purchase->getCalculatedPrices())) {
            return $purchase;
        }

        // Make calculated prices for purchase
        $purchasePrice = $this->purchasePriceFactory->create($purchase, $this->currencyManager->get());

        $calculatedPricesMatrix = $this->createPurchaseCalculatedPricesMatrix($purchasePrice);

        if ($purchase->getTransportation()) $this->makeCalculatedPricesService($purchase->getTransportation(), $purchasePrice);
        if ($purchase->getPaymentType()) $this->makeCalculatedPricesService($purchase->getPaymentType(), $purchasePrice);

        $purchase->setCalculatedPrices((array)$calculatedPricesMatrix);

        return $purchase;
    }

    public function makeCalculatedPricesForPurchaseProductVariant(
        PurchaseProductVariant      $purchaseProductVariant,
        ?ProductVariantPriceContext $context = null
    ): PurchaseProductVariant
    {
        if (!empty($purchaseProductVariant->getCalculatedPrices())) {
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
    ): Purchase
    {
        $context = $this->resolveVariantContext($context);

        $this->makeCalculatedPricesForPurchase($purchase);
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $this->makeCalculatedPricesForPurchaseProductVariant($purchaseProductVariant, $context);
        }

        return $purchase;
    }

    public function makeCalculatedPricesService(
        PaymentType|Transportation  $service,
        Purchase|PurchasePrice|null $purchase = null): PaymentType|Transportation
    {
        return $service->setCalculatedPrices(
            (array)$this->createServiceCalculatedPricesMatrix($service, $purchase)
        );
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
    ): PurchaseCalculatedPricesMatrix
    {
        $additionalPurchaseCosts = new AdditionalPurchaseCostMatrix();

        $purchasePrice->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $additionalPurchaseCosts->addFromArray($purchasePrice->getAdditionalCosts(), 'priceVat');
        $priceVat = $purchasePrice->getPrice(true);
        $priceVatNoServices = $purchasePrice->getPrice(false);

        $purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $additionalPurchaseCosts->addFromArray($purchasePrice->getAdditionalCosts(), 'priceNoVat');
        $priceNoVat = $purchasePrice->getPrice(true);
        $priceNoVatNoServices = $purchasePrice->getPrice(false);


        $purchasePrice->setVatCalculationType(VatCalculationType::WithVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
        $additionalPurchaseCosts->addFromArray($purchasePrice->getAdditionalCosts(), 'priceVatNoDiscount');
        $priceVatNoDiscount = $purchasePrice->getPrice(true);
        $priceVatNoDiscountNoServices = $purchasePrice->getPrice(false);

        $purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT)
            ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
        $additionalPurchaseCosts->addFromArray($purchasePrice->getAdditionalCosts(), 'priceNoVatNoDiscount');
        $priceNoVatNoDiscount = $purchasePrice->getPrice(true);
        $priceNoVatNoDiscountNoServices = $purchasePrice->getPrice(false);

        return new PurchaseCalculatedPricesMatrix(
            priceVat: $priceVat,
            priceNoVat: $priceNoVat,
            priceVatNoDiscount: $priceVatNoDiscount,
            priceNoVatNoDiscount: $priceNoVatNoDiscount,
            priceVatNoServices: $priceVatNoServices,
            priceNoVatNoServices: $priceNoVatNoServices,
            priceVatNoDiscountNoServices: $priceVatNoDiscountNoServices,
            priceNoVatNoDiscountNoServices: $priceNoVatNoDiscountNoServices,
            additionalPurchaseCosts: $additionalPurchaseCosts->getData()
        );
    }

    protected function createVariantCalculatedPricesCollection(ProductVariantPrice $productVariantPrice, array $amounts): array
    {
        $calculatedPricesCollection = [];

        foreach ($amounts as $minimalAmount) {
            $productVariantPrice->setAmount($minimalAmount);
            $calculatedPricesMatrix = $this->createVariantCalculatedPricesMatrix($productVariantPrice);
            $calculatedPricesCollection[$minimalAmount] = (array)$calculatedPricesMatrix;
        }
        return $calculatedPricesCollection;
    }

    protected function resolveVariantContext(?ProductVariantPriceContext $context): ProductVariantPriceContext
    {
        return $context ?? new ProductVariantPriceContext(
            currencyOrConversionRate: $this->currencyManager->get()
        );
    }

    protected function findCheapestVariantPriceForProduct(
        Product                     $product,
        ?ProductVariantPriceContext $context = null
    ): ?ProductVariantPrice
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
        if (!$cheapestPrice) {
            return null;
        }

        $cheapestVariantPrice = $this->productVariantPriceFactory->entityLoadFromContext($cheapestPrice, $context);
        return $cheapestVariantPrice;
    }

    protected function createServiceCalculatedPricesMatrix(
        PaymentType|Transportation  $service,
        PurchasePrice|null $purchase = null
    ): ServiceCalculatedPrices
    {
        $serviceCalculatedPrices = new ServiceCalculatedPrices();
        if (!$purchase) {
            $currency = $this->currencyManager->get();
            $serviceCalculatedPrices->priceNoVat = $this->serviceCalculationUtils->calculateServicePrice($service, $currency, VatCalculationType::WithoutVAT);
            $serviceCalculatedPrices->priceVat = $this->serviceCalculationUtils->calculateServicePrice($service, $currency, VatCalculationType::WithVAT);
            return $serviceCalculatedPrices;
        }

        if ($purchase instanceof Purchase) {
            $purchase = $this->purchasePriceFactory->create($purchase, $this->currencyManager->get());
        }
        $fn = match ($this->entityManager->getMetadataFactory()->getMetadataFor(get_class($service))->getName()) {
            Transportation::class => 'getTransportationPrice',
            PaymentType::class => 'getPaymentPrice',
        };
        $purchase->setVatCalculationType(VatCalculationType::WithoutVAT);
        $serviceCalculatedPrices->priceNoVat = $purchase->$fn();

        $purchase->setVatCalculationType(VatCalculationType::WithVAT);
        $serviceCalculatedPrices->priceVat = $purchase->$fn();

        return $serviceCalculatedPrices;
    }
}