<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Controller\WebController;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\ContactMessage;
use Greendot\EshopBundle\Form\ContactFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class GeneralPageController extends AbstractController implements WebController
{

    #[Route(
        path: '/{slug}',
        name: 'web_get_page',
        options: ['expose' => true]

    )]
    public function getPage(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Category $category,
        UrlGeneratorInterface $urlGenerator,
    ): Response
    {
        if (!$category) {
            return new RedirectResponse($urlGenerator->generate('web_homepage'));
        }

        $title = $category->getTitle();
        if ($category->getTitle() === null || $category->getTitle() === '') {
            $title = $category->getName();
        }

        $template = 'web/pages/empty_page.html.twig';
        if ($category->getCategoryType()) {
            $template = $category->getCategoryType()->getTemplate();
        }

        return $this->render($template, [
            'title'            => $title,
            'category'         => $category,
            'replaced_content' => $category->getHtml()
        ]);
    }

}
