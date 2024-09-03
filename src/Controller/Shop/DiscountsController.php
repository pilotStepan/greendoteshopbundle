<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DiscountsController extends AbstractController
{
    #[Route('/slevy', name: 'discounts_index')]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findDiscountedProducts();

        $preparedProducts = array_map(function ($product) {
            return [
                'id'           => $product->getId(),
                'name'         => $product->getName(),
                'slug'         => $product->getSlug(),
                'mainImage'    => $product->getUpload() ? $product->getUpload()->getPath() : null,
                'availability' => $product->getAvailability(),
            ];
        }, $products);

        return $this->render('shop/category/discounts.html.twig', [
            'products' => $preparedProducts,
        ]);
    }
}
