<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use DeepL\DeepLException;
use DeepL\Translator;
use Doctrine\ORM\EntityManagerInterface;

class GenerateTranslation
{
    private EntityManagerInterface $entityManager;
    private ProductVariantRepository $productVariantRepository;
    public function __construct(EntityManagerInterface $entityManager, ProductVariantRepository $productVariantRepository)
    {
        $this->entityManager = $entityManager;
        $this->productVariantRepository = $productVariantRepository;
    }

    /**
     * @throws DeepLException
     */
    public function translateCategory(Category $category, $targetLocale): void
    {
        $category->setTranslatableLocale($targetLocale);

        $translator = new Translator($_ENV['DEEPL_API_KEY']);
        if ($category->getHtml()){
            $html = $translator->translateText($category->getHtml(), 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
            $category->setHtml($html->text);
        }

        if ($category->getDescription()){
            $desc = $translator->translateText($category->getDescription(), 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
            $category->setDescription($desc->text);
        }

        if ($category->getName()){
            $name = $translator->translateText($category->getName(), 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
            $category->setName($name->text);
            $category->setMenuName($name->text);
            $category->setTitle($name->text);
        }

        $this->entityManager->persist($category);
    }

    /**
     * @throws DeepLException
     */
    public function translateProduct(Product $product, $targetLocale): void
    {
        $product->setTranslatableLocale($targetLocale);
        $translator = new Translator($_ENV['DEEPL_API_KEY']);

        if ($product->getTextGeneral()){
            $html = $translator->translateText($product->getTextGeneral(), 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
            $product->setTextGeneral($html->text);
        }

        if ($product->getName()){
            $name = $translator->translateText($product->getName(), 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
            $product->setName($name->text);
            $product->setMenuName($name->text);
            $product->setTitle($name->text);
            $product->setDescription($name->text);
            $productName = $name->text;
        }

        if ($product->getProductVariants()){
            $variants = $product->getProductVariants();
            foreach ($variants as $variant){
                $variant =  $this->productVariantRepository->find($variant);
                $variant->setTranslatableLocale($targetLocale);
                if ($variant->getName() && $variant->isIsActive()){
                    if (isset($productName) && str_contains($variant->getName(), "|")){
                        $splitArray = explode("|", $variant->getName());
                        $slicedArray = array_slice($splitArray, 1);
                        $joinedString = implode(" ", $slicedArray);
                        $variantName = $translator->translateText($joinedString, 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
                        $name = $productName." |".$variantName->text;
                        $variant->setName($name);
                    } else {
                        $name = $translator->translateText($variant->getName(), 'EN', strtoupper($targetLocale), ['tag_handling' => 'html']);
                        $variant->setName($name->text);
                    }
                }
                $this->entityManager->persist($variant);
            }
        }

        $this->entityManager->persist($product);
    }
}