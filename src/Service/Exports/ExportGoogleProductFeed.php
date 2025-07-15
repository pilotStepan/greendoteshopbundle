<?php

namespace Greendot\EshopBundle\Service\Exports;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ExportRepository;
use Greendot\EshopBundle\Service\Exports\ExportBase;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ExportGoogleProductFeed extends ExportBase
{
    private readonly string $url;
    public function __construct(
        Filesystem $filesystem,
        ExportRepository $exportRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        CurrencyRepository $currencyRepository,
        #[Autowire('%kernel.project_dir%/public/exports/')]
        $directory,
        private readonly ParameterBagInterface $parameterBag,
        private readonly ProductVariantPriceFactory $productVariantPriceFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ProductInfoGetter $productInfoGetter
    )
    {
        parent::__construct(
            'google_product_feed', 'google_product_feed.xml', 'channel',
            $directory,$filesystem, $exportRepository, $entityManager,$messageBus, $currencyRepository);
        $this->url = $parameterBag->get('greendot_eshop.global.absolute_url') ?? 'https://www.example.com';
    }

    function generateItem(int $objectId): ?string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        $productVariant = $this->entityManager->getRepository(ProductVariant::class)->find($objectId);
        $productVariantPrice = $this->productVariantPriceFactory->create($productVariant, $this->currency ,1);


        $product = $productVariant->getProduct();
        $product->setTranslatableLocale($this->locale);
        $this->entityManager->refresh($product);


        $item = new \SimpleXMLElement("<item></item>");
        //$item = $channel->addChild('item');

        $item->addChild("{$host}:g:id", $productVariant->getId());
        $item->addChild('title', $product->getName());
        $description = htmlspecialchars(strip_tags($product->getTextGeneral()), ENT_XML1);
        if (strlen($description) > 3000){
            $description = substr($description, 0, 3000);
        }
        $item->addChild('description', $description);
        try {
            $item->addChild('link', $this->url . $this->urlGenerator->generate('shop_product', ['slug' => $product->getSlug(), 'variant' => $productVariant->getId(), '_locale' => $this->locale]));
        }catch (\Exception $exception){
            $item->addChild('link', $this->url . $this->urlGenerator->generate('shop_product', ['slug' => $product->getSlug(), 'variant' => $productVariant->getId()]));
        }

        $discountedPrice = $productVariantPrice->getPrice();
        $productVariantPrice->setDiscountCalculationType(DiscountCalculationType::WithoutDiscount);
        $undiscountedPrice = $productVariantPrice->getPrice();


        $item->addChild("{$host}:g:price", $this->formattedNumber($discountedPrice) . $this->currency->getName());
        if ($discountedPrice and $undiscountedPrice != $discountedPrice and $discountedPrice < $undiscountedPrice) {
            $item->addChild("{$host}:g:sale_price", $this->formattedNumber($discountedPrice) . $this->currency->getName());
        }

        $item->addChild("{$host}:g:condition", 'new');
        $item->addChild("{$host}:g:age_group", 'adult');


        $identifierExists = false;
        if ($product->getProducer()?->getName()) {
            $identifierExists = true;
            $item->addChild("{$host}:g:brand", $product->getProducer()->getName());
        }
        if ($productVariant->getExternalId()) {
            $identifierExists = true;
            $item->addChild("{$host}:g:mpn", $productVariant->getExternalId());
        }
        if (!$identifierExists) {
            $item->addChild("{$host}:g:identifier_exists", FALSE);
        }

        if ($productVariant->getProduct()?->getUpload()?->getPath()) {
            $item->addChild("{$host}:g:image_link", $this->url . $productVariant->getProduct()->getUpload()->getPath());
        }
//        else {
//            $item->addChild("{$host}:g:image_link", $this->url . '/build/img/nenifoto.jpg');
//        }

        //$item->addChild('bdl:g:availability', $variant->getStock() == 0 ? "out_of_stock" : "in_stock");
        $item->addChild("{$host}:g:availability",  "in_stock");
        $googleBreadCrumbs = $this->googleFormattedBreadCrumbs($product);
        if ($googleBreadCrumbs) {
            $item->addChild("{$host}:g:product_type", $googleBreadCrumbs);
        }

        //$item->addChild('bdl:g:google_product_category', 'PLACEHOLDER');

        $item->addChild("{$host}:g:item_group_id", $product->getId());

//        $transportationPrice = $this->priceCalculator->transportationPrice($this->transportation, VatCalculationType::WithVAT, $this->currency);
//
//        $shipping = $item->addChild("{$host}:g:shipping");
//        $shipping->addChild("{$host}:g:country", $this->countryString);
//        $shipping->addChild("{$host}:g:price", $this->formattedNumber($transportationPrice) . $this->currencyString);

        $formattedString = $this->removeXMLDeclaration($item->asXML());
        return $this->addLineBreakBetweenTags($formattedString);
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
                $category = $this->entityManager->getRepository(Category::class)->find($category->getId());
                $category->setTranslatableLocale($this->locale);
                $this->entityManager->refresh($category);

                $return [] = $category->getName();
                try {
                    $this->entityManager->detach($category);
                } catch (\Exception $exception) {
                }
            }
            if ($return) {
                return implode(" > ", $return);
            }
        }
        return null;
    }

    function generateHead(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        $xml = new \SimpleXMLElement("<rss></rss>");
        $xml->addAttribute('version', '2.0');
        $xml->addAttribute("{$host}:xmlns:g", 'http://base.google.com/ns/1.0');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', "{$host} RSS 2.0 data feed");
        $channel->addChild('description', "{$host} data feed");
        $channel->addChild('link', $this->url);

        return $xml->asXML();
    }
}