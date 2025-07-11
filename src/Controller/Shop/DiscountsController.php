<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DiscountsController extends AbstractController
{
    #[Route('/{slug}', name: 'shop_discounts_products')]
    public function index(Category $category): Response
    {
        return $this->render('shop/discounts/products.html.twig', [
            'title' => $category->getTitle(),
            'category' => $category,
        ]);
    }
}
