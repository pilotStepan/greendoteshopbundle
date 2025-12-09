<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Attribute\TranslatableRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Enum\ReservedCategoryIds;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MasterController extends AbstractController
{
    #[TranslatableRoute(class: Category::class, property: 'slug')]
    #[Route('/{slug}', name: 'app_master', options: ['expose' => true], priority: 1)]
    public function index(Category $category): Response
    {
        if ($category->getId() === ReservedCategoryIds::BLOG->value) {
            return $this->forward('Greendot\EshopBundle\Controller\Web\BlogController::blogLandingPage', ['blogSlug' => $category->getSlug()]);
        }

        if ($category->getCategoryType() && $category->getCategoryType()->getControllerName()) {
            return $this->forward($category->getCategoryType()->getControllerName(), ['slug' => $category->getSlug()]);
        } else {
            return $this->forward('Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage', ['slug' => $category->getSlug()]);
        }
    }
}
