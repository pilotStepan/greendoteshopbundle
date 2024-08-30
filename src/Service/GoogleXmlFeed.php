<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ExportRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Greendot\EshopBundle\Service\LLGServices\LLGDataGetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleXmlFeed
{
    private readonly Currency $currency;
    private readonly Transportation $transportation;
    private readonly string $countryString;     //ISO3166 country code
    private readonly string $currencyString;    //ISO4217 currency string
    private readonly string $absoluteUrl;

    private readonly string $exportFileName;

    public function __construct(
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly UrlGeneratorInterface    $urlGenerator,
        private readonly PriceCalculator          $priceCalculator,
        private readonly TransportationRepository $transportationRepository,
        private readonly CurrencyRepository       $currencyRepository,
        private readonly LLGDataGetter            $LLGDataGetter,
        private readonly ProductInfoGetter        $productInfoGetter,
        private readonly EntityManagerInterface   $entityManager,
        private readonly Filesystem               $filesystem,
        private readonly ExportRepository         $exportRepository
    )
    {
        $this->currency = $this->currencyRepository->findOneBy(['isDefault' => 1]);
        $this->transportation = $this->transportationRepository->findOneBy(['isEnabled' => 1, 'country' => 'CZ']);

        $this->absoluteUrl = "https://bdl.cz";
        $this->currencyString = " CZK";
        $this->countryString = "CZ";

        $this->exportFileName = "googleFeed.xml";
    }

    /**
     * @deprecated true
     * @return void
     * @throws \Exception
     */
    private function generateXmlForCategory(): void
    {

        /**
         * explanation of the structure for the namespace
         * normally the namespace is just 'g:child_name' but for some reason SimpleXMLElement, even tough it is the best way to create XML, does not support the namespaces properly,
         * so we need to use a work-around which consist of simply prepending another namespace element,
         * so the final namespace declaration looks like this 'bdl:g:child_name'
         * the 'bdl' keyword is arbitrary value and doesn't represent anything, it is just a value that we know will be removed in the compiling of the XML
         */

        /**
         * @todo: rozdelit tuto funkci do funkce na zalozeni souboru a append
         * @todo: pridat persist do Export tabulky
         * @todo: vytvorit message a message handler (synchrone)
         * @todo: to vse pouzit v message handeleru
         */

        $categoryProductVariants = $this->productVariantRepository->findXmlExportableProducts(20, 0);

        $xml = new \SimpleXMLElement("<rss></rss>");
        $xml->addAttribute('version', '2.0');
        $xml->addAttribute('bdl:xmlns:g', 'http://base.google.com/ns/1.0');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', 'BDL RSS 2.0 data feed');
        $channel->addChild('description', 'BDL data feed');
        $channel->addChild('link', $this->absoluteUrl);

        foreach ($categoryProductVariants as $variant) {
            $price = $this->priceCalculator->calculateProductVariantPrice($variant, $this->currency, VatCalculationType::WithVAT, DiscountCalculationType::WithoutDiscount, 0);
            if (!$price) {
                continue;
            }
            $priceSale = $this->priceCalculator->calculateProductVariantPrice($variant, $this->currency, VatCalculationType::WithVAT, DiscountCalculationType::WithDiscount, 0);

            $item = $channel->addChild('item');

            $item->addChild("bdl:g:id", $variant->getId());
            $item->addChild('title', $variant->getProduct()->getName());
            $item->addChild('description', strip_tags($variant->getProduct()->getTextGeneral()));
            $item->addChild('link', $this->absoluteUrl . $this->urlGenerator->generate('shop_product', ['slug' => $variant->getProduct()->getSlug(), 'highlightvariant' => $variant->getId()]));

            $item->addChild('bdl:g:price', $this->formattedNumber($price) . $this->currencyString);
            if ($priceSale and $price != $priceSale and $priceSale < $price) {
                $item->addChild('bdl:g:sale_price', $this->formattedNumber($priceSale) . $this->currencyString);
            }

            $item->addChild('bdl:g:condition', 'new');
            $item->addChild('bdl:g:age_group', 'adult');

            $bdlCode = $this->LLGDataGetter->getBdlCode($variant);
            $manufacturer = $this->LLGDataGetter->getManufacturer($variant->getProduct());

            $identifierExists = false;
            if ($manufacturer) {
                $identifierExists = true;
                $item->addChild('bdl:g:brand', $manufacturer);
            }
            if ($bdlCode) {
                $identifierExists = true;
                $item->addChild('bdl:g:mpn', $bdlCode);
            }
            if (!$identifierExists) {
                $item->addChild('bdl:g:identifier_exists', FALSE);
            }

            if ($variant->getProduct()?->getUpload()?->getPath()) {
                $item->addChild('bdl:g:image_link', $this->absoluteUrl . $variant->getProduct()->getUpload()->getPath());
            } else {
                $item->addChild('bdl:g:image_link', $this->absoluteUrl . '/build/img/nenifoto.jpg');
            }

            //$item->addChild('bdl:g:availability', $variant->getStock() == 0 ? "out_of_stock" : "in_stock");
            $item->addChild('bdl:g:availability',  "in_stock");
            $googleBreadCrumbs = $this->googleFormattedBreadCrumbs($variant->getProduct());
            if ($googleBreadCrumbs) {
                $item->addChild('bdl:g:product_type', $googleBreadCrumbs);
            }
            //$item->addChild('bdl:g:google_product_category', 'PLACEHOLDER');//todo
            $item->addChild('bdl:g:item_group_id', $variant->getProduct()->getId());

            $transportationPrice = $this->priceCalculator->transportationPrice($this->transportation, VatCalculationType::WithVAT, $this->currency);

            $shipping = $item->addChild('bdl:g:shipping');
            $shipping->addChild('bdl:g:country', $this->countryString);
            $shipping->addChild('bdl:g:price', $this->formattedNumber($transportationPrice) . $this->currencyString);

            $this->entityManager->detach($variant);

        }
        $this->saveXmlExport($xml->asXML());
    }


    /**
     * @param string $type
     * @type $type string = 'all' - returns the whole xml
     * @type $type string = 'start' - returns only the started elements without closure
     * @type $type string = 'end' - returns only the closing elements
     *
     * @return string|null
     */
    private function generateHead(string $type = "all"): ?string
    {
        if (!in_array($type, ["all", "start", "end"])) {
            return null;
        }
        $xml = new \SimpleXMLElement("<rss></rss>");
        $xml->addAttribute('version', '2.0');
        $xml->addAttribute('bdl:xmlns:g', 'http://base.google.com/ns/1.0');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', 'BDL RSS 2.0 data feed');
        $channel->addChild('description', 'BDL data feed');
        $channel->addChild('link', $this->absoluteUrl);

        if ($type == "all") {
            return $xml->asXML();
        } else {
            return $this->splitXmlByElement($xml->asXML(), "channel")[$type];
        }
    }

    /**
     * Splits base xml into start part and end part, by element_name.
     *
     * Used if we do not use the SimpleXML for the whole process of creating XML. So we can prepend start and append end.
     *
     * Used by $this->generateHead(string) function.
     *
     * @param string $xml
     * @param string $element_name
     * @return array {start: string, end: string}
     */
    private function splitXmlByElement(string $xml, string $element_name = "channel"): array
    {
        $xml = trim($xml);
        // Find the position of the opening and closing tags of the specified element
        $end_tag = '</' . $element_name . '>';

        $end_tag_position = strpos($xml, $end_tag);
        $end = substr($xml, $end_tag_position);
        $start = substr($xml, 0, $end_tag_position);

        // Return an array containing starting and closing elements
        return array("start" => $start, "end" => $end);
    }

    /**
     * Generates XML element for Google XML Feed.
     * Element is called item.
     *
     * @param ProductVariant $variant
     * @return string|null
     * @throws \Exception
     */
    private function generateItem(ProductVariant $variant): ?string
    {

        $price = $this->priceCalculator->calculateProductVariantPrice($variant, $this->currency, VatCalculationType::WithVAT, DiscountCalculationType::WithoutDiscount, 0);
        if (!$price) {
            return null;
        }
        $priceSale = $this->priceCalculator->calculateProductVariantPrice($variant, $this->currency, VatCalculationType::WithVAT, DiscountCalculationType::WithDiscount, 0);

        $product = $variant->getProduct();
        $product->setTranslatableLocale('cs');
        $this->entityManager->refresh($product);
        $variant->setProduct($product);


        $item = new \SimpleXMLElement("<item></item>");
        //$item = $channel->addChild('item');

        $item->addChild("bdl:g:id", $variant->getId());
        $item->addChild('title', $variant->getProduct()->getName());
        $item->addChild('description', htmlspecialchars(strip_tags($variant->getProduct()->getTextGeneral()), ENT_XML1));
        $item->addChild('link', $this->absoluteUrl . $this->urlGenerator->generate('shop_product', ['slug' => $variant->getProduct()->getSlug(), 'highlightvariant' => $variant->getId(), '_locale' => 'cs']));

        $item->addChild('bdl:g:price', $this->formattedNumber($price) . $this->currencyString);
        if ($priceSale and $price != $priceSale and $priceSale < $price) {
            $item->addChild('bdl:g:sale_price', $this->formattedNumber($priceSale) . $this->currencyString);
        }

        $item->addChild('bdl:g:condition', 'new');
        $item->addChild('bdl:g:age_group', 'adult');

        $bdlCode = $this->LLGDataGetter->getBdlCode($variant);
        $manufacturer = $this->LLGDataGetter->getManufacturer($variant->getProduct());

        $identifierExists = false;
        if ($manufacturer) {
            $identifierExists = true;
            $item->addChild('bdl:g:brand', $manufacturer);
        }
        if ($bdlCode) {
            $identifierExists = true;
            $item->addChild('bdl:g:mpn', $bdlCode);
        }
        if (!$identifierExists) {
            $item->addChild('bdl:g:identifier_exists', FALSE);
        }

        if ($variant->getProduct()?->getUpload()?->getPath()) {
            $item->addChild('bdl:g:image_link', $this->absoluteUrl . $variant->getProduct()->getUpload()->getPath());
        } else {
            $item->addChild('bdl:g:image_link', $this->absoluteUrl . '/build/img/nenifoto.jpg');
        }

        //$item->addChild('bdl:g:availability', $variant->getStock() == 0 ? "out_of_stock" : "in_stock");
        $item->addChild('bdl:g:availability',  "in_stock");
        $googleBreadCrumbs = $this->googleFormattedBreadCrumbs($variant->getProduct());
        if ($googleBreadCrumbs) {
            $item->addChild('bdl:g:product_type', $googleBreadCrumbs);
        }

        //$item->addChild('bdl:g:google_product_category', 'PLACEHOLDER');

        $item->addChild('bdl:g:item_group_id', $variant->getProduct()->getId());

        $transportationPrice = $this->priceCalculator->transportationPrice($this->transportation, VatCalculationType::WithVAT, $this->currency);

        $shipping = $item->addChild('bdl:g:shipping');
        $shipping->addChild('bdl:g:country', $this->countryString);
        $shipping->addChild('bdl:g:price', $this->formattedNumber($transportationPrice) . $this->currencyString);

        return $this->removeXMLDeclaration($item->asXML());
    }

    /**
     * Removes the XML starter element.
     *
     * Used after creating element that will be appended into file as text. Where <xml ?> already is.
     *
     * @param string $xmlString
     * @return string
     */
    private function removeXMLDeclaration(string $xmlString): string
    {
        return trim(preg_replace('/<\?xml.*\?>/', '', $xmlString));
    }

    /**
     * Formats float to comply with Google standards.
     * Also, to keep the format same across the whole xml file
     *
     * @param float $number
     * @return string
     */
    private function formattedNumber(float $number): string
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * Creates bread-crumbs formatted as google product type
     *
     * @param Product $product
     * @return string|null
     */
    private function googleFormattedBreadCrumbs(Product $product): ?string
    {
        $categoryBreadcrumbs = $this->productInfoGetter->getProductBreadCrumbsArray($product);
        if ($categoryBreadcrumbs) {
            $return = [];
            foreach ($categoryBreadcrumbs as $category) {
                $category->setTranslatableLocale('cs');
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

    /**
     * Creates XML file only with started elements. (They should be closed with $this->endExport())
     * If there already is any temp file it throws error.
     *
     * @return void
     * @throws \Exception
     */
    public function startExport(): void
    {
        $content = $this->generateHead("start");

        $relativeFilePath = "public/exports/temp_" . $this->exportFileName;
        if ($this->filesystem->exists($relativeFilePath)) {
            throw new \Exception("File '" . $relativeFilePath . "' already exists.");
        }

        $this->filesystem->dumpFile($relativeFilePath, $content);
    }

    /**
     * If there is temp file it adds end tags to make it valid.
     * Used in combination with $this->startExport()
     *
     * @return void
     * @throws \Exception
     */
    public function endExport(): void
    {
        $content = $this->generateHead('end');
        $relativeFilePath = "public/exports/temp_" . $this->exportFileName;

        if (!$this->filesystem->exists($relativeFilePath)) {
            throw new \Exception("File '" . $relativeFilePath . "' does not exist.");
        }

        $this->filesystem->appendToFile($relativeFilePath, $content);
    }

    /**
     * renames temp xml file to final name state (temp_googleFeed.xml -> googleFeed.xml)
     * persists Export of said file into database
     * also renames the old one (adds time stamp) - the file and in the DB
     *
     * @return void
     * @throws \Exception
     */
    public function tempToFinal(): void
    {
        $this->entityManager->clear();
        $exportsDirPath = "public/exports/"; //public file where exports are stored
        $tempRelativeName = "temp_" . $this->exportFileName; //temp name
        $finalRelativeName = $this->exportFileName; //final state name
        $renamedRelativeName = time() . '_' . $this->exportFileName; //old file name

        /**
         * check if there even is any temp file it can rename
         */
        if (!$this->filesystem->exists($exportsDirPath . $tempRelativeName)) {
            throw new \Exception("File '" . $exportsDirPath . $tempRelativeName . "' does not exist. Could not changed it to final");
        }

        /**
         * checks for old file with 'final state', if there is one, ite renames it (as a file and in DB)
         */
        if ($this->filesystem->exists($exportsDirPath . $finalRelativeName)) {
            $oldFinalExport = $this->exportRepository->findOneBy(["filename" => $finalRelativeName, "type" => "google_xml_feed"]);
            $this->filesystem->rename($exportsDirPath.$finalRelativeName, $exportsDirPath.$renamedRelativeName);
            if ($oldFinalExport){
                $oldFinalExport->setFilename($renamedRelativeName);
                $this->entityManager->persist($oldFinalExport);
            }
        }


        /**
         * renames temp file to 'final state' and creates Export record of that file
         */
        $this->filesystem->rename($exportsDirPath.$tempRelativeName, $exportsDirPath.$finalRelativeName);
        $export = new Export();
        $export->setDate(new \DateTime("now"));
        $export->setType('google_xml_feed');
        $export->setFilename($finalRelativeName);
        $this->entityManager->persist($export);

        $this->entityManager->flush();
    }

    /**
     * appends item into temp file
     *
     * @param ProductVariant $productVariant
     * @return void
     * @throws \Exception
     */
    public function appendItem(ProductVariant $productVariant):void
    {
        $tempRelativePath = "public/exports/temp_".$this->exportFileName;
        if (!$this->filesystem->exists($tempRelativePath)){
            throw new \Exception("Temp file not found. ( ".$tempRelativePath." )");
        }
        $itemXml = $this->generateItem($productVariant);
        $this->filesystem->appendToFile($tempRelativePath, $itemXml);
    }

}
