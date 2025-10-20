<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class MenuController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private CategoryInfoGetter $categoryInfoGetter
    ) {}

    #[Route('/_fragment/shop_menu', name: 'shop_menu')]
    public function index(): Response
    {
        return $this->render('shop/menu/shop.html.twig', [
            'categories' => $this->categoryRepository->findMenuCategoriesByMenuID(2),
        ]);
    }

    #[Route('/_fragment/shop_menu_open', name: 'shop_menu_open', defaults: ["category" => 0], options: ['expose' => true])]
    #[Route('/_fragment/shop_menu_open-{category}', name: 'shop_menu_open_category', options: ['expose' => true])]
    public function shopOpen(Request $request, ?int $category = 0): Response
    {
        $currentCategory = $category !== 0
            ? $this->categoryRepository->findHinted($category)
            : $request->attributes->get("category");

        $superCategory = $currentCategory
            ? $this->categoryInfoGetter->getMostSuperCategoryOfCategory($currentCategory)
            : null;

        return $this->render('shop/menu/shop_menu_open.html.twig', [
            'categories'                     => $this->categoryRepository->findMenuCategoriesByMenuID(2),
            'currentCategory'                => $currentCategory,
            'superCategoryOfCurrentCategory' => $superCategory
        ]);
    }

    #[CustomApiEndpoint]
    #[Route('/api/shop_menu/superCategoryOfCurrentCategory/{category}', name: 'api_superCategory')]
    public function getSuperCategoryOfCurrentCategory(
        Category            $category,
        SerializerInterface $serializer
    ): JsonResponse
    {
        $data = [
            'categories'                     => $this->categoryRepository->findMenuCategoriesByMenuID(2),
            'currentCategory'                => $category,
            'superCategoryOfCurrentCategory' => $this->categoryInfoGetter->getMostSuperCategoryOfCategory($category)
        ];

        return new JsonResponse(
            $serializer->serialize($data, 'json', ['groups' => 'category_default']),
            JsonResponse::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/_fragment/basic_menu', name: 'basic_menu')]
    public function basic(): Response
    {
        return $this->render('shop/menu/basic.html.twig', [
            'categories' => $this->categoryRepository->findMenuCategoriesByMenuID(2),
        ]);
    }

    #[Route('/_fragment/mobile_nav', name: 'fragment_mobile_nav')]
    public function mobileNav(Request $request): Response
    {
        return $this->render('shop/menu/mobile_nav.html.twig', [
            'categories'      => $this->categoryRepository->findMenuCategoriesByMenuID(2),
            'otherCategories' => $this->categoryRepository->findMenuCategoriesByMenuID(1),
            'locale'          => $request->getLocale(),
            'request'         => $request
        ]);
    }
}
