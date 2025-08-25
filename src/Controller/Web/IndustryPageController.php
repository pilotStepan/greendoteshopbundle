<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndustryPageController extends AbstractController
{
    #[Route('/{slug}', name: 'app_web_industry_page')]
    public function index(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Category $category
    ): Response
    {
        return $this->render('web/industry_page/index.html.twig', [
            'category' => $category,
            'title'    => $category->getTitle(),
        ]);
    }
}
