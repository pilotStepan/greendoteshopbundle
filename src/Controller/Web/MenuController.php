<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MenuController extends AbstractController
{
    #[Route('/_fragment/generate-menu/{id}', name: 'web_menu')]
    public function generateMenu(
        MenuType           $menuType,
        CategoryRepository $categoryRepository,
        Request            $request): Response
    {
        $categories = $categoryRepository->findMenuCategories($menuType);

        return $this->render('/' . $menuType->getTemplate(), [
            'categories' => $categories,
            'request'    => $request,
            'menuType'   => $menuType
        ]);
    }

    #[Route('/_fragment/generate-sub-menu-{subMenuType}/category-{category}/for-menu-{menuType}', name: 'web_sub_menu')]
    public function generateSubMenu(
        SubMenuType        $subMenuType,
        Category           $category,
        MenuType           $menuType,
        CategoryRepository $categoryRepository,
        RequestStack       $requestStack): Response
    {
        $request = $requestStack->getMainRequest();

        $locale = $request->getLocale();

        $categories = $categoryRepository->findSubMenuCategories($category, $subMenuType);
        return $this->render($subMenuType->getTemplate(), [
            'categories'      => $categories,
            'locale'          => $locale,
            'request'         => $request,
            'menuType'        => $menuType,
            'subMenuType'     => $subMenuType,
            'parent_category' => $category
        ]);
    }
}
