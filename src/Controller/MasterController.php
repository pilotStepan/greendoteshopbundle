<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MasterController extends AbstractController
{

    #[Route('/{slug}', name: 'app_master', options: ['expose' => true], priority: 1)]
    public function index(string $slug, CategoryRepository $categoryRepository, ParameterBagInterface $parameterBag): Response
    {
        $availableLocales = $parameterBag->get('app.available.locales');
        if ($availableLocales and in_array($slug, $availableLocales)){
            return $this->forward('Greendot\EshopBundle\Controller\Web\HomepageController::index', ['_locale' => $slug]);
        }

        $category = $categoryRepository->findOneByHinted(['slug' => $slug]);
        if (!$category){
            return $this->createNotFoundException('Category not found');
        }

        if ($category->getCategoryType() && $category->getCategoryType()->getControllerName()) {
            return $this->forward($category->getCategoryType()->getControllerName(), ['slug' => $category->getSlug()]);
        } else {
            return $this->forward('Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage', ['slug' => $category->getSlug()]);
        }
    }
}
