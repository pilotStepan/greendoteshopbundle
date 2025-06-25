<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    #[Route('/{slug}_c', name: 'shop_category')]
    public function index(
        Category           $category,
        ProductRepository  $productRepository,
        PaginatorInterface $paginator,
        Request            $request): Response
    {
        /*
         * TODO rework top selling products - join category products and tops selling in one query.
         */
        $topSellingProducts = $productRepository->findCategoryProducts($category, 2);

        //$topSellingProducts = $productRepository->findTopSellingProducts($products, 2);
        $categoryTemplate   = $category->getCategoryType()->getTemplate();

        //$pagination = $paginator->paginate($products, $request->query->getInt('page', 1), 24);
        //$pagination->setTemplate('pagination/pagination.html.twig');

        return $this->render($categoryTemplate, [
            'category'           => $category,
            'topSellingProducts' => $topSellingProducts,
            //'pagination'         => $pagination
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

    #[Route("/api/categories", name: 'api_search_categories', options: ['expose' => true], methods: ['GET'])]
    public function searchCategories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $name = $request->query->getString('name');
        $types = $request->query->all('type');

        /* @var CategoryTypeEnum[] $typeEnums */
        $typeEnums = array_map(
            fn($type) => CategoryTypeEnum::tryFrom((int)$type),
            $types
        );

        $categories = $categoryRepository->searchByNameAndTypes($name, $typeEnums, 20);

        $categories = array_map(function (Category $category) {
            return [
                'id'   => $category->getId(),
                'name' => $category->getName(),
                'menu_name' => $category->getMenuName(),
                'title' => $category->getTitle(),
                'slug' => $category->getSlug(),
                'upload_path' => $category->getUpload()?->getPath() ?: '',
            ];
        }, $categories);

        return new JsonResponse($categories, 200);
    }
}
