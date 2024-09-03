<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApplicationPageController extends AbstractController
{
    #[Route('/{slug}', name: 'app_web_application_page')]
    public function index(Category $category): Response
    {
        return $this->render($category->getCategoryType()->getTemplate(), [
            'category' => $category,
            'title'    => $category->getTitle(),
        ]);
    }

    #[Route('/{slug}', name: 'app_web_application_message')]
    public function message(Category $category): Response
    {
        return $this->render($category->getCategoryType()->getTemplate(), [
            'category' => $category,
            'title'    => $category->getTitle(),
        ]);
    }
}
