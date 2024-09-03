<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FragmentController extends AbstractController
{
    #[Route('/_fragment/breadCrumbs/category-{category}/json-{json}', name: 'fragment_category_breadCrumbs', options: ['expose' => true], defaults: ['product' => null, 'json' => false])]
    #[Route('/_fragment/breadCrumbs/product-{product}/json-{json}', name: 'fragment_product_breadCrumbs', options: ['expose' => true], defaults: ['category' => null, 'json' => false])]
    public function fragmentBreadCrumbs(?Category $category, ?Product $product, $json, CategoryInfoGetter $categoryInfoGetter, ProductInfoGetter $productInfoGetter, Request $request):Response
    {
        if ($category) {
            $breadCrumbs = $categoryInfoGetter->getCategoryBreadCrumbsArray($category);
        } elseif ($product) {
            $breadCrumbs = $productInfoGetter->getProductBreadCrumbsArray($product, $request);
        } else {
            $breadCrumbs = [];
        }

        $html =  $this->render('components/_fragment-breadCrumbs.html.twig', [
            'crumbs' => $breadCrumbs,
            'product' => $product
        ]);

        return ($json ? new JsonResponse($html->getContent(), 200) : $html);
    }
}
