<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Category;use Greendot\EshopBundle\Entity\Project\Product;use Greendot\EshopBundle\Entity\Project\ProductVariant;use Greendot\EshopBundle\Entity\Project\Purchase;use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;use Greendot\EshopBundle\Enum\DiscountCalculationType;use Greendot\EshopBundle\Enum\VatCalculationType;use Greendot\EshopBundle\Repository\Project\CurrencyRepository;use Greendot\EshopBundle\Repository\Project\ParameterGroupRepository;use Greendot\EshopBundle\Repository\Project\ParameterRepository;use Greendot\EshopBundle\Repository\Project\ProductRepository;use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;use Symfony\Bundle\SecurityBundle\Security;

class GoogleAnalytics
{
    public function __construct(
        readonly private PriceCalculator $priceCalculator,
        readonly private ProductRepository $productRepository,
        readonly private ProductVariantRepository $productVariantRepository,
        readonly private CurrencyRepository $currencyRepository,
        readonly private ParameterRepository $parameterRepository,
        readonly private ParameterGroupRepository $parameterGroupRepository,
        readonly private ProductInfoGetter $productInfoGetter,
        readonly private Security $security
    ){}

    /**
     * @param ProductVariant $productVariant
     * @param string $type -> ('inquiry', 'cart')
     * @return array
     */
    public function addToCart(ProductVariant $productVariant,$amount = 1, string $type = "cart"): array
    {
        if (!in_array($type, ['cart', 'inquiry'])){
            return ['GA Error: Wrong type given.'];
        }

        try {
            $currency = $this->currencyRepository->findOneBy(['isDefault' => 1]);
            $purchaseTemp = new Purchase();
            if ($this->security->getUser()){
                $purchaseTemp->setClient($this->security->getUser());
            }else{
                $purchaseTemp->setClient(null);
            }
            $purchaseTemp->setDateIssue(new \DateTime('now'));
            $purchaseProductVariantTemp = new PurchaseProductVariant();
            $purchaseProductVariantTemp->setProductVariant($productVariant);
            $purchaseProductVariantTemp->setPurchase($purchaseTemp);
            $purchaseProductVariantTemp->setAmount($amount);
            $price = $this->priceCalculator->calculateProductVariantPrice($purchaseProductVariantTemp, $currency, VatCalculationType::WithoutVAT, DiscountCalculationType::WithDiscount, false);
        }catch (\Exception $exception){
            $price  = 0;
        }

        try {
            $producerGroup = $this->parameterGroupRepository->findOneBy(['name' => 'Manufacturer']);
            $producer = $this->parameterRepository->getDistinctDataOfParameterGroupForProductVariantArray($producerGroup, [$productVariant->getId()]);

            if (count($producer) > 0){
                $producer = $producer[0]->getData();
            }else{
                $producer = $productVariant->getProduct()->getProducer()->getName();
            }

            $dataLayer = [
                'event' => 'add_to_'.$type,
                'ecommerce' => [
                    'items' => [
                        'item_id' => $productVariant->getProduct()->getId(),
                        'item_name' => $productVariant->getProduct()->getName(),
                        'item_brand' => $producer,
                        'item_category' => $productVariant->getProduct()->getCategoryProducts()[0]->getCategory()->getName(),
                        'item_variant' => $productVariant->getName(),
                        'price' => $price,
                        'quantity' => $amount
                    ]
                ]
            ];
        }catch (\Exception $exception){
            $dataLayer = [];
        }

        return $dataLayer;
    }

    /**
     * @param Category $category
     * @return array
     */
    public function viewCategory(Category $category): array
    {
        return [
            'event' => 'view_category',
            'ecommerce' => [
                'category_name' => $category->getName(),
                'category_id' => $category->getId()
            ]
        ];
    }

    public function viewItem(Product $product):array
    {
        try {
            $currency = $this->currencyRepository->findOneBy(['isDefault' => 1]);
            $producerGroup = $this->parameterGroupRepository->findOneBy(['name' => 'Manufacturer']);
            $producer = $this->parameterRepository->getDistinctDataOfParameterGroupForProductVariantArray($producerGroup, [$product->getProductVariants()->first()->getId()]);

            if (count($producer) > 0){
                $producer = $producer[0]->getData();
            }else{
                $producer = $product->getProducer()->getName();
            }

            $productVariants = [];
            foreach ($product->getProductVariants() as $productVariant){
                $productVariants []= [
                    'item_variant_name' => $productVariant->getName()
                ];
            }

            $dataLayer = [
                'event' => 'view_item',
                'ecommerce' => [
                    'item_name' => $product->getName(),
                    'item_id' => $product->getId(),
                    'category' => $product->getCategoryProducts()[0]->getCategory()->getName(),
                    'item_brand' => $producer,
                    'item_variants' => $productVariants,
                    'priceFrom' => $this->productInfoGetter->getProductPriceString($product,$currency, true)
                ]
            ];
        }catch (\Exception $exception){
            $dataLayer = [];
        }

        return $dataLayer;
    }

    public function purchase(Purchase $purchase): array
    {
        $ecommerce = $this->getPurchaseEcommerceArray($purchase);
        if ($ecommerce and !empty($ecommerce)){
            return [
                'event' => 'purchase',
                'ecommerce' => $ecommerce
            ];
        }else{
            return [];
        }
    }

    public function inquiry(Purchase $purchase): array
    {
        $ecommerce = $this->getPurchaseEcommerceArray($purchase);
        if ($ecommerce and !empty($ecommerce)){
            return [
                'event' => 'inquiry_list',
                'ecommerce' => $ecommerce
            ];
        }else{
            return [];
        }
    }


    private function getPurchaseEcommerceArray(Purchase $purchase){
        try {
            $currency = $this->currencyRepository->findOneBy(['isDefault' => 1]);
            $producerGroup = $this->parameterGroupRepository->findOneBy(['name' => 'Manufacturer']);

            $currencyName = $currency?->getSymbol() == "Kč" ? "CZK" : null;

            $email = $purchase?->getClient()?->getMail();


            $items = [];
            foreach ($purchase->getProductVariants() as $purchaseProductVariant){
                $producer = $this->parameterRepository->getDistinctDataOfParameterGroupForProductVariantArray($producerGroup, [$purchaseProductVariant->getProductVariant()->getId()]);

                if (count($producer) > 0){
                    $producer = $producer[0]->getData();
                }else{
                    $producer = $purchaseProductVariant->getProductVariant()->getProduct()->getProducer()->getName();
                }

                $items [] = [
                    'item_id' => $purchaseProductVariant->getProductVariant()->getId(),
                    'item_name' => $purchaseProductVariant->getProductVariant()->getId(),
                    'price' => $this->priceCalculator->calculateProductVariantPrice($purchaseProductVariant, $currency, VatCalculationType::WithoutVAT, DiscountCalculationType::WithDiscount, false),
                    'quantity' => $purchaseProductVariant->getAmount(),
                    'item_brand' => $producer,
                    'item_category' => $purchaseProductVariant->getProductVariant()->getProduct()->getCategoryProducts()[0]->getCategory()->getName(),
                ];
            }


            $dataLayer = [
                    'transaction_id' => $purchase->getId(),
                    'currency' =>$currencyName,
                    'shipping' => $this->priceCalculator->transportationPrice($purchase, VatCalculationType::WithoutVAT, $currency),
                    'value' => $this->priceCalculator->calculatePurchasePrice($purchase, $currency, VatCalculationType::WithoutVAT, null, DiscountCalculationType::WithDiscount, 1 ),
                    'items' => $items,
                    'email' => $email
            ];
        }catch (\Exception $exception){
            $dataLayer = [];
        }

        return $dataLayer;
    }
}