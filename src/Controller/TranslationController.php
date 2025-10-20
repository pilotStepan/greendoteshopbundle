<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Greendot\EshopBundle\Service\GenerateTranslation;
use DeepL\DeepLException;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslationController extends AbstractController
{
    #[Route('/translate/menu', name: 'app_translation')]
    public function index(): Response
    {
        return $this->render('translation/index.html.twig', [
            'controller_name' => 'TranslationController',
        ]);
    }

    /**
     * @throws DeepLException
     */
    #[CustomApiEndpoint]
    #[Route('/translate/category-{id}', name: 'app_category_translation', defaults: ['id' => 0])]
    public function category(Category $category, EntityManagerInterface $entityManager, GenerateTranslation $generateTranslation): Response
    {
        $generateTranslation->translateCategory($category, 'cs');
        $entityManager->flush();
        return new Response('success', 200);
    }

    /**
     * @throws DeepLException
     */
    #[CustomApiEndpoint]
    #[Route('/translate/shop/category-{id}', name: 'app_shop_category_translation', defaults: ['id' => 0])]
    public function categoryShop(Category $category,CategoryInfoGetter $categoryInfoGetter, EntityManagerInterface $entityManager, GenerateTranslation $generateTranslation): Response
    {
        $categories = $categoryInfoGetter->getAllSubCategories($category);
        foreach ($categories as $category){
            $generateTranslation->translateCategory($category, 'cs');
        }
        $entityManager->flush();
        return new Response('success', 200);
    }

    /**
     * @throws DeepLException
     */
    #[CustomApiEndpoint]
    #[Route('/translate/product-{id}', name: 'app_product_translation', defaults: ['id' => 0])]
    public function product(Product $product, EntityManagerInterface $entityManager, GenerateTranslation $generateTranslation): Response
    {
        $generateTranslation->translateProduct($product, 'cs');
        $entityManager->flush();
        return new Response('success', 200);
    }

    /**
     * @throws DeepLException
     */
    #[Route('/translate/shop/category-products/category-{id}-offset-{offset}', name: 'app_shop_category_products_translation', defaults: ['id' => 0])]
    public function categoryProductsShop(Category $category,int $offset, ProductRepository $productRepository, EntityManagerInterface $entityManager, GenerateTranslation $generateTranslation, CategoryInfoGetter $categoryInfoGetter): Response
    {
        set_time_limit(0);
        $refreshAfter = 100;
        $subCategories = $categoryInfoGetter->getAllSubCategories($category);
        $translated = 0;
        $allProducts = [];
        foreach ($subCategories as $subCategory){
            $products = $productRepository->findCategoryProducts($subCategory);
            $allProducts = array_merge($products, $allProducts);
        }
        $offsetProducts = array_slice($allProducts, $offset, $refreshAfter);
        foreach ($offsetProducts as $product){
            $generateTranslation->translateProduct($product, 'cs');
            $translated++;
        }
        $entityManager->flush();
        if ($translated === $refreshAfter){
            return $this->redirectToRoute('app_shop_category_products_translation', ['id' => $category->getId(), 'offset' => $offset+$translated]);
        }
        return new Response('Prelozeno '.$offset+$translated.' produktu', 200);
    }

    #[CustomApiEndpoint]
    #[Route('/translate/javascript/locale-{locale}', name: 'translate_js')]
    public function translateJS($locale, TranslatorInterface $translator, Request $request): Response
    {
        $defaultLocale = 'en';
        $translations = $translator->getCatalogue($defaultLocale)->all('messages+intl-icu');
        $resultArray = array();

        if ($locale == $defaultLocale){
           $resultArray = $translations;
        } else {
            foreach ($translations as $key => $value) {
                $resultArray[$key] = $key;
            }
        }
        return new Response(json_encode($resultArray, JSON_UNESCAPED_UNICODE), 200);

    }
}
