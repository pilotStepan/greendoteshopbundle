<?php

namespace Greendot\EshopBundle\Export\Data\Factory;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Export\Data\Model\GoogleProductFeedModel;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class GoogleProductFeedFactory
{

    private readonly string $url;

    public function __construct(
        private readonly ProductInfoGetter $productInfoGetter,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CurrencyManager $currencyManager,
        private readonly SluggerInterface $slugger,
        ParameterBagInterface $parameterBag
    ){
        $this->url = $parameterBag->get('greendot_eshop.global.absolute_url') ?? 'https://www.example.com';
    }

    public function create(ProductVariant $productVariant): GoogleProductFeedModel
    {
        $product = $productVariant->getProduct();

        $description = htmlspecialchars(strip_tags($product->getTextGeneral()), ENT_XML1);
        if (strlen($description) > 3000){
            $description = substr($description, 0, 3000);
        }
        try {
            $link = $this->url . $this->urlGenerator->generate('shop_product', ['slug' => $product->getSlug(), 'variant' => $productVariant->getId()]);
        }catch (InvalidParameterException $invalidParameterException){
            $slug = $this->slugger->slug($product->getSlug() ?? $product->getName())->lower()->toString();
            $link = $this->url . $this->urlGenerator->generate('shop_product', ['slug' => $slug, 'variant' => $productVariant->getId()]);
        }
        $imageLink = $productVariant?->getUpload()?->getPath();
        if ($imageLink){
            $imageLink = $this->url.$imageLink;
        }


        $brand = $product?->getProducer()?->getName();
        $externalId = $productVariant?->getExternalId();

        $calculatedPrices = $productVariant->getCalculatedPrices() ?? [];
        if (!empty($calculatedPrices)){
            $calculatedPrices = $calculatedPrices[array_key_first($calculatedPrices)];
        }
        $price = $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceVatNoDiscount');
        $priceDiscount = $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceVat');
        if ($price <= $priceDiscount){
            $priceDiscount = null;
        }
        $currency = $this->currencyManager->get();

        return new GoogleProductFeedModel(
            id: $productVariant->getId(),
            title: $product->getTitle() ?? $product->getName(),
            description: $description,
            link: $link,
            price: $price ? $this->formattedNumber($price).$currency->getName() : null,
            salePrice: $priceDiscount ? $this->formattedNumber($priceDiscount).$currency->getName() : null,
            condition: 'new',
            ageGroup: 'adult',
            brand: $brand,
            mpn: $externalId,
            identifier_exists: ($externalId or $brand),
            image_link: $imageLink,
            availability: 'in_stock',
            productType: $this->googleFormattedBreadCrumbs($product),
            itemGroupId: $product->getId()
        );
    }

    public function getFromCalculatedPricesSafe(array $calculatedPrices, string $key): float
    {
        if (isset($calculatedPrices[$key])) {
            return $calculatedPrices[$key];
        }
        return 0;
    }


    private function formattedNumber(float $number): string
    {
        return number_format($number, 2, '.', '');
    }

    private function googleFormattedBreadCrumbs(Product $product): ?string
    {
        $categoryBreadcrumbs = $this->productInfoGetter->getProductBreadCrumbsArray($product);
        if ($categoryBreadcrumbs) {
            $return = [];
            foreach ($categoryBreadcrumbs as $category) {
                $return [] = $category->getName();
            }
            if ($return) {
                return implode(" > ", $return);
            }
        }
        return null;
    }
}