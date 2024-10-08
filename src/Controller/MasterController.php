<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MasterController extends AbstractController
{

    #[Route('/{slug}', name: 'app_master', options: ['expose' => true], priority: 1)]
    public function index(Category $category, $slug): Response
    {
        if ($category->getCategoryType() && $category->getCategoryType()->getControllerName()) {
            return $this->forward($category->getCategoryType()->getControllerName(), ['slug' => $category->getSlug()]);
        } else {
            return $this->forward('Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage', ['slug' => $category->getSlug()]);
        }
    }
}
