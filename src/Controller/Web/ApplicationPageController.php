<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Attribute\TranslatableRoute;
use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApplicationPageController extends AbstractController
{
    #[TranslatableRoute(class: Category::class, property: 'slug')]
    #[Route('/{slug}', name: 'app_web_application_page')]
    public function index(
        Category $category
    ): Response
    {
        return $this->render($category->getCategoryType()->getTemplate(), [
            'category' => $category,
            'title'    => $category->getTitle(),
        ]);
    }

    #[TranslatableRoute(class: Category::class, property: 'slug')]
    #[Route('/{slug}', name: 'app_web_application_message')]
    public function message(
        Category $category
    ): Response
    {
        return $this->render($category->getCategoryType()->getTemplate(), [
            'category' => $category,
            'title'    => $category->getTitle(),
        ]);
    }
}
