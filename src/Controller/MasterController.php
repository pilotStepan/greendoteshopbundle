<?php

namespace Greendot\EshopBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Enum\ReservedCategoryIds;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MasterController extends AbstractController
{

    #[Route('/{slug}', name: 'app_master', options: ['expose' => true], priority: 1)]
    public function index(string $slug, CategoryRepository $categoryRepository): Response
    {
        $category = $categoryRepository->findOneByHinted(['slug' => $slug]);
        if (!$category) {
            return $this->createNotFoundException('Category not found');
        }
        assert($category instanceof Category);
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
