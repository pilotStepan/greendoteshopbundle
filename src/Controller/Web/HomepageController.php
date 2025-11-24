<?php

namespace Greendot\EshopBundle\Controller\Web;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Controller\WebController;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomepageController extends AbstractController implements WebController
{
    #[Route(
        path: '/',
        name: 'web_homepage',
        options: ['expose' => true],
        priority: 999
    )]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $category = $categoryRepository->findOneByHinted(['id' => 1]);


        return $this->render('web/homepage/index.html.twig', [
            'title' => $category->getTitle(),
            'current_slug' => '',
            'category' => $category,
            'replaced_content' => $category->getHtml(),
        ]);
    }

    #[Route(path: "/_fragment/sale_products", name: "fragment_sale_products")]
    public function sale_products(): Response
    {
        return $this->render("web/homepage/_fragment_sale_products.html.twig", []);
    }
}
