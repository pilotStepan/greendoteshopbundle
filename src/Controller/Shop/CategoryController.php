<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    #[Route('/{slug}_c', name: 'shop_category')]
    public function index(
        Category           $category,
        ProductRepository  $productRepository,
        PaginatorInterface $paginator,
        Request            $request): Response
    {
        $products = $productRepository->findCategoryProducts($category);

        $topSellingProducts = $productRepository->findTopSellingProducts($products, 2);
        $categoryTemplate   = $category->getCategoryType()->getTemplate();

        $pagination = $paginator->paginate($products, $request->query->getInt('page', 1), 24);
        $pagination->setTemplate('pagination/pagination.html.twig');

        return $this->render($categoryTemplate, [
            'category'           => $category,
            'topSellingProducts' => $topSellingProducts,
            'pagination'         => $pagination
        ]);
    }



    #[Route('/_fragment/allUnderCategories-{category}', name: 'under_categories')]
    public function fragUnderCategories(Category $category, CategoryInfoGetter $categoryInfoGetter): JsonResponse
    {
        $categories = $categoryInfoGetter->getAllSubCategories($category);
        $apiString  = [];

        foreach ($categories as $category) {
            array_push($apiString, $category->getId());
        }

        return $this->json(json_encode($apiString));
    }

    #[Route('/_request/changeCurrency/{currency}', name: 'request_change_currency')]
    public function changeCurrency(Currency $currency, Request $request, Session $session): RedirectResponse
    {
        $session->set('selectedCurrency', $currency);

        return $this->redirect($request->headers->get('referer'));
    }
}
