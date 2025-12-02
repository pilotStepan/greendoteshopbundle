<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Attribute\TranslatableRoute;
use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndustryPageController extends AbstractController
{
    #[TranslatableRoute(class: Category::class, property: 'slug')]
    #[Route('/{slug}', name: 'app_web_industry_page')]
    public function index(
        Category $category
    ): Response
    {
        return $this->render('web/industry_page/index.html.twig', [
            'category' => $category,
            'title'    => $category->getTitle(),
        ]);
    }
}
