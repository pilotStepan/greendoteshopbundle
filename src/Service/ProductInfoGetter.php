<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Symfony\Component\HttpFoundation\Request;

class ProductInfoGetter
{
    public function __construct(
        private ProductRepository        $productRepository,
        private ProductVariantRepository $productVariantRepository,
        private PriceCalculator          $priceCalculator,
        private CategoryInfoGetter       $categoryInfoGetter,
        private PriceRepository          $priceRepository
    )
    {
    }
    //javascript:void(0)

    /**
     * @param ProductVariant $productVariant
     * @return string
     */
    public function getProductAuthor(ProductVariant $productVariant)
    {
        $author = 'Autor neznámý';
        if ($productVariant != null) {
            $parameters = $productVariant->getParameters();
            foreach ($parameters as $parameter) {
                if ($parameter->getParameterGroup()->getId() == 1) {
                    $author = $parameter->getData();
                }
            }
        }
        return $author;
    }

    public function hasActiveProduct(Category $category)
    {
        $products = count($this->productRepository->findCategoryProducts($category));
        if ($products > 0) {
            return true;
        } else {
            return false;
        }
    }

    //returns string that is used in vue renders, (example results: od 90 Kč, €5, zdarma)
    public function getProductPriceString(Product $product, Currency $currency, $discount = true): string|int
    {
        $finalString = "";

        if ($discount === true) {
            $discountType = DiscountCalculationType::WithDiscountPlusAfterRegistrationDiscount;
        } else {
            $discountType = DiscountCalculationType::WithoutDiscount;
        }

        $prices          = [];
        /*
         * TODO change to normal product variant listing, no need to use repository and load these again
         */
        $productVaraints = $this->productVariantRepository->findBy(['product' => $product]);
        foreach ($productVaraints as $variant) {
            /*
             * TODO create array of prices, do not round (should be rounded by frontend)
             * $prices[] = ['priceNoVat' => $calculatedPriceNoVat, 'priceVat' => $calculatedPriceVat, 'priceNoVatNoDiscount' => $calculatedPriceNoVatNoDiscount, 'priceVatNoDiscount' => $calculatedPriceVatNoDiscount]
             */
            $calculatedPrice = $this->priceCalculator->calculateProductVariantPrice($variant, $currency, VatCalculationType::WithoutVAT, $discountType, true, true);
            if (!in_array($calculatedPrice, $prices) and $calculatedPrice != 0) {
                $prices[] = $calculatedPrice;
            }
        }

        if (empty($prices)) {
            $finalString = 0;
        } else {
            if (count($prices) > 1) {
                $finalString .= "Od ";
            }
            /*
             * TODO porovnat podle priceNoVat
             */
            $finalString .= min($prices) . " " . $currency->getSymbol();
        }

        return $finalString;
    }

    public function getProductDiscount(Product $product): ?int
    {
        $cheapestPrice = $this->priceRepository->findCheapestPriceForProduct($product);

        if ($cheapestPrice && $cheapestPrice->getDiscount() !== null) {
            return (int)round($cheapestPrice->getDiscount());
        }

        return null;
    }

    public function getProductBreadCrumbsArray(Product $product, ?Request $request = null): array
    {
        $referer      = $request?->headers->get('referer');
        $returnArrays = [];
        foreach ($product->getCategoryProducts() as $categoryProduct) {
            $categoryCrumbs  = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($categoryProduct->getCategory());
            $returnArrays [] = $categoryCrumbs;
        }
        if ($referer) {
            foreach ($returnArrays as $bredCrumbsArray) {
                foreach ($bredCrumbsArray as $category) {
                    if (str_contains($referer, $category->getSlug())) {
                        return $bredCrumbsArray;
                    }
                }
            }
        }

        return $returnArrays[0];

    }

    public function getProductsForEntity($entity, $onlyActive = true)
    {
        return $this->productRepository->getProductsForEntity($entity, $onlyActive);
    }

}