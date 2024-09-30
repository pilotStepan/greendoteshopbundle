<?php

namespace Greendot\EshopBundle\Twig;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Note;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Upload;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\NoteRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Greendot\EshopBundle\Service\GoogleAnalytics;
use Greendot\EshopBundle\Service\ManageWorkflows;
use Greendot\EshopBundle\Service\PriceCalculator;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Greendot\EshopBundle\Service\ValueAddedTaxCalculator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductRepository            $productRepository,
        private readonly PriceCalculator              $priceCalculator,
        private readonly ProductInfoGetter            $productInfoGetter,
        private readonly CurrencyRepository           $currencyRepository,
        private readonly ParameterRepository          $parameterRepository,
        private readonly PriceRepository              $priceRepository,
        private readonly ValueAddedTaxCalculator      $addedTaxCalculator,
        private readonly CategoryInfoGetter           $categoryInfoGetter,
        private readonly ManageWorkflows              $manageWorkflows,
        private readonly NoteRepository               $noteRepository,
        private readonly GoogleAnalytics              $googleAnalytics,
        private readonly CategoryRepository           $categoryRepository,
        private readonly RequestStack                 $requestStack,
        private readonly RouterInterface              $router,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_price', [$this, 'getProductPriceString']),
            new TwigFunction('product_price_old', [$this, 'getProductPriceStringWithoutDiscount']),
            new TwigFunction('product_discount', [$this, 'getProductDiscount']),

            new TwigFunction('get_product_author', [$this, 'getProductAuthor']),
            new TwigFunction('has_active_product', [$this, 'hasActiveProduct']),

            new TwigFunction('calculate_purchase_price', [$this, 'calculatePurchasePrice']),
            new TwigFunction('calculate_product_variant_price', [$this, 'calculateProductVariantPrice']),

            new TwigFunction('transportation_price', [$this, 'transportationPrice']),
            new TwigFunction('payment_price', [$this, 'paymentPrice']),

            new TwigFunction('count_items_in_purchase', [$this, 'countItemsInPurchase']),

            new TwigFunction('get_currencies', [$this, 'getCurrencies']),
            new TwigFunction('get_default_currency', [$this, 'getDefaultCurrency']),
            new TwigFunction('calculate_theoretical_discounted_price', [$this, 'calculateTheoreticalDiscountedPrice']),
            new TwigFunction('price_table_array', [$this, 'priceTableArray']),

            new TwigFunction('sized_image', [$this, 'getSizedImage']),

            new TwigFunction('get_purchase_product_variant_state_meta', [$this, 'getPurchaseProductVariantStateMeta']),

            new TwigFunction('get_all_sub_categories', [$this, 'getAllSubCategories']),

            new TwigFunction('get_note', [$this, 'getNote']),
            new TwigFunction('convert_price_to_currency', [$this, 'convertPriceToCurrency']),

            new TwigFunction('get_google_analytics_array', [$this, 'getGoogleAnalyticsArray']),

            new TwigFunction('get_vat', [$this, 'getVat']),
            new TwigFunction('get_no_vat', [$this, 'getNoVat']),
            new TwigFunction('get_total', [$this, 'getTotal']),
            new TwigFunction('get_total_vat', [$this, 'getTotalVat']),
            new TwigFunction('get_total_no_vat', [$this, 'getTotalNoVat']),

            new TwigFunction('get_blog_article_publish_date', [$this, 'getBlogArticlePublishDate']),
            new TwigFunction('get_blog_articles_by_label', [$this, 'getBlogArticlesByLabel']),

            new TwigFunction('get_route_for_locale', [$this, 'getRouteForLocale']),

            new TwigFunction('format_price', [$this, 'formatPrice']),
            new TwigFunction('get_currency_from_session', [$this, 'getCurrencyFromSession']),
        ];
    }

    public function formatPrice(float $price, ?Currency $currency = null): string
    {
//        if ($price == 0) return 'Zdarma';
        $currency ??= $this->requestStack->getCurrentRequest()->getSession()->get('selectedCurrency');
        $formattedPrice = number_format($price, 2);

        return $currency->isSymbolLeft()
            ? sprintf('%s %s', $currency->getSymbol(), $formattedPrice)
            : sprintf('%s %s', $formattedPrice, $currency->getSymbol());
    }

    public function getCurrencyFromSession($symbolOnly = false): Currency|string {
        $currency = $this->requestStack->getCurrentRequest()->getSession()?->get('selectedCurrency')
            ?? $this->currencyRepository->findOneBy(['isDefault' => true]);
        return $symbolOnly ? $currency->getSymbol() : $currency;
    }

    public function getRouteForLocale(string $locale): string
    {
        $route = "web_homepage";
        $route_params = [];
        if ($this->requestStack?->getMainRequest()?->attributes) {
            $mainRequestAttr = $this->requestStack->getMainRequest()->attributes;
            if ($mainRequestAttr->get('_route')) {
                $route = $mainRequestAttr->get('_route');
            }
            if ($mainRequestAttr->get('_route_params')) {
                $route_params = $mainRequestAttr->get('_route_params');
            }
        }
        $route_params["_locale"] = $locale;

        return $this->router->generate($route, $route_params);
    }

    public function getProductDiscount($productID): ?int
    {
        $product = $this->productRepository->find($productID);

        if (!$product) {
            return null;
        }

        return $this->productInfoGetter->getProductDiscount($product);
    }

    public function getProductPriceString($productID, ?Currency $currency = null): string|int
    {
        $product = $this->productRepository->find($productID);

        if ($currency === null) {
            $currency = $this->getDefaultCurrency();
        }

        return $this->productInfoGetter->getProductPriceString($product, $currency);
    }

    public function getProductPriceStringWithoutDiscount($productID, ?Currency $currency = null): string|int
    {
        $product = $this->productRepository->find($productID);

        if ($currency === null) {
            $currency = $this->getDefaultCurrency();
        }

        return $this->productInfoGetter->getProductPriceString($product, $currency, false);
    }

    public function getVat($price, $vat)
    {
        return $this->addedTaxCalculator->getVat($price, $vat);
    }

    public function getNoVat($price, $vat)
    {
        return $this->addedTaxCalculator->getNoVat($price, $vat);
    }

    public function getTotal($order, $discount = true)
    {
        return $this->addedTaxCalculator->getTotal($order, $discount);
    }

    public function getTotalVat($order, $vat = null, $discount = true)
    {
        return $this->addedTaxCalculator->getTotalVat($order, $vat, $discount);
    }

    public function getTotalNoVat($order, $vat, $discount = true)
    {
        return $this->addedTaxCalculator->getTotalNoVat($order, $vat, $discount);
    }

    /*
    public function getGHSImagesForProduct(Product $product): array
    {
        $parameterGroupType = $this->parameterGroupTypeRepository->findOneBy(['name' => 'GHS']);
        $parameters = $this->parameterRepository->findDistinctResultsByParameterGroupTypeForProduct($product, $parameterGroupType );
        $returnArray = [];
        if ($parameters and !empty($parameters)){
            foreach ($parameters as $parameter){
                $code = $parameter->getParameterGroup()->getName();
                $returnArray[$code] = "/build/img/".$code.".png";
            }
        }
        return $returnArray;

    }*/

    public function getGoogleAnalyticsArray(string $type, mixed $value): false|string|array
    {
        $return = [];
        if ($type == "view_category" and $value instanceof Category) {
            $return = $this->googleAnalytics->viewCategory($value);
        } elseif ($type == "view_item" and $value instanceof Product) {
            $return = $this->googleAnalytics->viewItem($value);
        } elseif ($type == "add_to_cart" and $value instanceof ProductVariant) {
            $return = $this->googleAnalytics->addToCart($value);
        } elseif ($type == "add_to_inquiry" and $value instanceof ProductVariant) {
            $return = $this->googleAnalytics->addToCart($value, 'inquiry');
        } elseif ($type == "purchase" and $value instanceof Purchase) {
            $return = $this->googleAnalytics->purchase($value);
        } elseif ($type == "send_inquiry" and $value instanceof Purchase) {
            $return = $this->googleAnalytics->inquiry($value);
        }
        return $return;
        //return json_encode($return);

    }

    public function getAllSubCategories(Category $category)
    {
        return $this->categoryInfoGetter->getAllSubCategories($category);
    }

    public function getNote(Purchase $purchase, string $type): Note
    {
        return $this->noteRepository->findOneBy(['purchase' => $purchase->getId(), 'type' => $type]);
    }

    public function getSizedImage(Upload $upload, string|null $sizeString = null): string
    {
        $path = $upload->getPath();
        if ($sizeString) {
            $path_parts = pathinfo($path);

            $dirname = $path_parts['dirname'];
            $filename = $path_parts['filename'];
            $extension = $path_parts['extension'];

            $new_filename = $filename . $sizeString . '.' . $extension;
            $new_path = $dirname . '/' . $new_filename;

            return trim($new_path);
        } else {
            return $path;
        }
    }

    public function priceTableArray(ProductVariant $productVariant, Currency $currency, string $vatCalculationType, $singleItemPrice = null, $do_rounding = true): array
    {
        $vatCalculationType = VatCalculationType::from($vatCalculationType);
        $prices = $this->priceRepository->findPricesByDateAndProductVariantNotGrouped($productVariant, new \DateTime("now"), null);
        //$prices = $this->priceRepository->findBy(['productVariant' => $productVariant]);
        $tableArray = [];
        foreach ($prices as $price) {
            //$price = new Price();
            $purchase = new Purchase();
            $purchaseProductVariant = new PurchaseProductVariant();
            $purchaseProductVariant->setProductVariant($productVariant);
            $purchaseProductVariant->setAmount($price->getMinimalAmount());
            $purchaseProductVariant->setPurchase($purchase);
            //dd($this->priceCalculator->calculateProductVariantPrice($purchaseProductVariant, $currency, $vatCalculationType, DiscountCalculationType::WithDiscount, true));

            $calculatedPrice = $this->priceCalculator->calculateProductVariantPrice($purchaseProductVariant, $currency, $vatCalculationType, DiscountCalculationType::WithDiscount, $singleItemPrice, $do_rounding);
            $onePrice = 0;
            if ($calculatedPrice) {
                $onePrice = $calculatedPrice / $price->getMinimalAmount();
            }
            if ($calculatedPrice <= $this->priceCalculator->convertCurrency(50000, $currency)) {
                $tableArray[$price->getMinimalAmount()]['onePrice'] = $onePrice;
                $tableArray[$price->getMinimalAmount()]['fullPrice'] = $calculatedPrice;
            }
        }
        return $tableArray;
    }

    public function paymentPrice(Purchase $purchase, string $vatCalculationType): ?float
    {
        $vatCalculationType = VatCalculationType::from($vatCalculationType);

        return $this->priceCalculator->paymentPrice($purchase, $vatCalculationType);
    }

    public function transportationPrice(Purchase|Transportation $purchase, string $vatCalculationType, ?Currency $currency = null): ?float
    {
        if (!$currency) {
            $currency = $this->requestStack->getCurrentRequest()->getSession()->get('selectedCurrency');
        }

        $vatCalculationType = VatCalculationType::from($vatCalculationType);

        return $this->priceCalculator->transportationPrice($purchase, $vatCalculationType, $currency);
    }

    public function getCurrencies(): array
    {
        return $this->currencyRepository->findAll();
    }

    public function convertPriceToCurrency(float $price, Currency $currency): float
    {
        return $this->priceCalculator->convertCurrency($price, $currency);
    }

    public function getDefaultCurrency(): ?Currency
    {
        return $this->currencyRepository->findOneBy(["isDefault" => true]);
    }

    public function calculatePurchasePrice(Purchase $purchase, Currency $currency = null): float
    {
        if (!$currency) {
            $currency = $this->requestStack->getCurrentRequest()->getSession()->get('selectedCurrency');
        }

        return $this->priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );
    }

    public function calculateProductVariantPrice(PurchaseProductVariant $purchaseProductVariant, Currency $currency = null): float
    {
        if (!$currency) {
            $currency = $this->requestStack->getCurrentRequest()->getSession()->get('selectedCurrency');
        }

        return $this->priceCalculator->calculateProductVariantPrice(
            $purchaseProductVariant,
            $currency,
            VatCalculationType::WithVAT,
            DiscountCalculationType::WithDiscount,
            false,
            true
        );
    }

    public function countItemsInPurchase(Purchase $purchase): int
    {
        $count = 0;
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $count += $purchaseProductVariant->getAmount();
        }
        return $count;
    }

    public function getProductAuthor($productVariant): string
    {
        return $this->productInfoGetter->getProductAuthor($productVariant);
    }

    public function hasActiveProduct($category): string
    {
        return $this->productInfoGetter->hasActiveProduct($category);
    }

    public function getPurchaseProductVariantStateMeta(PurchaseProductVariant $purchaseProductVariant): ?string
    {
        $metadata = $this->manageWorkflows->getStateMetadata($purchaseProductVariant);
        if ($metadata and count($metadata) > 0) {
            return $metadata["description"];
        } else {
            return null;
        }
    }

    /*
    public function getVat($price, $vat)
    {
        return $this->priceCalculator->getVat($price, $vat);
    }

    public function getNoVat($price, $vat)
    {
        return $this->priceCalculator->getNoVat($price, $vat);
    }

    public function getTotal($order, $discount = true)
    {
        return $this->priceCalculator->getTotal($order, $discount);
    }

    public function getTotalVat($order, $vat = null, $discount = true)
    {
        return $this->priceCalculator->getTotalVat($order, $vat, $discount);
    }

    public function getTotalNoVat($order, $vat, $discount = true)
    {
        return $this->priceCalculator->getTotalNoVat($order, $vat, $discount);
    }

    public function getProductVariantPriceWithVat($productVariant): float
    {
        return $this->priceCalculator->getProductVariantPriceWithVat($productVariant);
    }

    public function getProductVariantPriceWithVatWithoutDiscount($productVariant): float
    {
        return $this->priceCalculator->getProductVariantPriceWithVatWithoutDiscount($productVariant);
    }

    public function getOrderProductVariantPriceWithVat($orderProductVariant): float
    {
        return $this->priceCalculator->getOrderProductVariantPriceWithVat($orderProductVariant);
    }

    public function getSubmittedOrderPriceWithVat($order): float
    {
        return $this->priceCalculator->getSubmittedOrderPriceWithVat($order);
    }

    public function getNotSubmittedOrderPriceWithVat($order): float
    {
        return $this->priceCalculator->getNotSubmittedOrderPriceWithVat($order);
    }

    public function getNotSubmittedOrderPriceWithoutVat($order): float
    {
        return $this->priceCalculator->getNotSubmittedOrderPriceWithoutVat($order);
    }

    public function getTransportationPrice($order, $transportation, $vat)
    {
        return $this->priceCalculator->getTransportationPrice($order, $transportation, $vat);
    }

    public function getPaymentPrice($payment, $order, $vat): float
    {
        return $this->priceCalculator->getPaymentPrice($payment, $order, $vat);
    }
    */


    public function getBlogArticlePublishDate(Category $category): ?string
    {
        try {
            $date = $this->parameterRepository->getSingleParameterByParameterGroupForCategory('Datum vydání', $category);
            return $date?->getData();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }

    public function getBlogArticlesByLabel(int $label, int $limit = 3)
    {
        return $this->categoryRepository->findBlogCategoriesByLabel($label, $limit);
    }
}
