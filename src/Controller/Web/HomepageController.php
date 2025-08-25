<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Controller\WebController;
use Greendot\EshopBundle\Entity\Project\ContactMessage;
use Greendot\EshopBundle\Form\ContactFormType;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\LabelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomepageController extends AbstractController implements WebController
{
    #[Route(
        path: '/',
        name: 'web_homepage',
        options: ['expose' => true]
    )]
    public function index(CategoryRepository $categoryRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = $categoryRepository->findOneBy(['id' => 1]);
        $category->setTranslatableLocale($request->getLocale());
        $entityManager->refresh($category);

        return $this->render('web/homepage/index.html.twig', [
            'title'            => $category->getTitle(),
            'current_slug'     => '',
            'category'         => $category,
            'replaced_content' => $category->getHtml()
        ]);
    }

    #[Route(path: "/_fragment/sale_products", name: "fragment_sale_products")]
    public function sale_products(): Response
    {
        return $this->render("web/homepage/_fragment_sale_products.html.twig", []);
    }
}
