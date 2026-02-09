<?php

namespace Greendot\EshopBundle\Service\Price;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;

//DO NOT MAKE FINAL!!!!
class CalculatedPricesService
{
    public function __construct(
        private ProductVariantPriceFactory  $productVariantPriceFactory,
        private PurchasePriceFactory        $purchasePriceFactory,
        private CurrencyManager             $currencyManager,
        private EntityManagerInterface      $entityManager,
    ) {}

    public function makeCalculatedPricesForProductVariant(ProductVariant $variant, $date = new \DateTime()) : ProductVariant
    {
        if (!empty($variant->getCalculatedPrices())){
            return $variant;
        }


        $qb = $this->entityManager->getRepository(Price::class)->createQueryBuilder('p');
        $uniqueMinimalAmounts = $qb
            ->select('DISTINCT p.minimalAmount')
            ->andWhere('p.productVariant = :variant')
            ->andWhere('p.validFrom <= :date')
            ->andWhere($qb->expr()->orX('p.validUntil IS NULL', 'p.validUntil >= :date'))
            ->setParameter('variant', $variant)
            ->setParameter('date', $date)
            ->orderBy('p.minimalAmount', 'ASC')
            ->getQuery()
            ->getSingleColumnResult()
        ;

        $uniqueMinimalAmounts = array_map('intval', $uniqueMinimalAmounts);
        
        
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
    public function makeCalculatedPricesForPurchase(Purchase $purchase) : Purchase
    {
        if (!empty($purchase->getCalculatedPrices())) {
            return $purchase;
        }

        // Make calculated prices for purchase
        $purchasePrice = $this->purchasePriceFactory->create($purchase, $this->currencyManager->get());

        $calculatedPricesObject = [];

        $purchasePrice->setVatCalculationType(VatCalculationType::WithVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $calculatedPricesObject['priceVat'] = $purchasePrice->getPrice(true);
        $calculatedPricesObject['priceVatNoServices'] = $purchasePrice->getPrice(false);


        $purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $calculatedPricesObject['priceNoVat'] = $purchasePrice->getPrice(true);
        $calculatedPricesObject['priceNoVatNoServices'] = $purchasePrice->getPrice(false);


        $purchasePrice->setVatCalculationType(VatCalculationType::WithVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);        
        $calculatedPricesObject['priceVatNoDiscount'] = $purchasePrice->getPrice(true);
        $calculatedPricesObject['priceVatNoDiscountNoServices'] = $purchasePrice->getPrice(false);

        $purchasePrice->setVatCalculationType(VatCalculationType::WithoutVAT)
                      ->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);        
        $calculatedPricesObject['priceNoVatNoDiscount'] = $purchasePrice->getPrice(true);
        $calculatedPricesObject['priceNoVatNoDiscountNoServices'] = $purchasePrice->getPrice(false);
        
        $purchase->setCalculatedPrices($calculatedPricesObject);

        // Make calculated prices for all purchaseVariants
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $this->makeCalculatedPricesForPurchaseProductVariant($purchaseProductVariant);
        }

        return $purchase;
    }

    public function makeCalculatedPricesForPurchaseProductVariant(PurchaseProductVariant $purchaseProductVariant) : PurchaseProductVariant
    {
        if (!empty($purchaseProductVariant->getCalculatedPrices())){
            return $purchaseProductVariant;
        }

        $calculatedPricesObject = [];

        $variantPrice = $this->productVariantPriceFactory->create($purchaseProductVariant, $this->currencyManager->get()); // price calculator object

        $variantPrice->setDiscountCalculationType(DiscountCalculationType::WithDiscount);
        $variantPrice->setVatCalculationType(VatCalculationType::WithVAT);
        $calculatedPricesObject['priceVat'] = $variantPrice->getPiecePrice();

        $variantPrice->setVatCalculationType(vatCalculationType::WithoutVAT);
        $calculatedPricesObject['priceNoVat'] = $variantPrice->getPiecePrice();

        $variantPrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
        $calculatedPricesObject['priceNoVatNoDiscount'] = $variantPrice->getPiecePrice();

        $variantPrice->setVatCalculationType(VatCalculationType::WithVAT);
        $calculatedPricesObject['priceVatNoDiscount'] = $variantPrice->getPiecePrice();

        $purchaseProductVariant->setCalculatedPrices($calculatedPricesObject);
            
        return $purchaseProductVariant;
    }


}