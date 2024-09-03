<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Controller\WebController;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\ContactMessage;
use Greendot\EshopBundle\Form\ContactFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\Service\dynamicReplacement;

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
    public function getPage(Category $category, UrlGeneratorInterface $urlGenerator, Request $request, EntityManagerInterface $entityManager, dynamicReplacement $dynamicReplacement): Response
    {
        $contactMessage = new ContactMessage();
        $form           = $this->createForm(ContactFormType::class, $contactMessage, ['attr' => ['class' => 'contact-form']]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->persist($contactMessage);
                $entityManager->flush();
                $this->addFlash(
                    'success',
                    'Zpráva byla odeslána!'
                );
                return new RedirectResponse($category->getSlug());
            } else {
                $this->addFlash(
                    'warning',
                    'Error'
                );
                return new RedirectResponse($category->getSlug());
            }
        }
        if ($category !== null) {
            if ($category->getTitle() === null || $category->getTitle() === '') {
                $title = $category->getName();
            } else {
                $title = $category->getTitle();
            }

            if ($category->getCategoryType()) {
                $template = $category->getCategoryType()->getTemplate();
            } else {
                $template = 'web/pages/empty_page.html.twig';
            }
            $replaced_content = $dynamicReplacement->dynamicCategoryReplace($category->getHtml());
            return $this->render($template, [
                'title'            => $title,
                'category'         => $category,
                'contact_form'     => $form->createView(),
                'replaced_content' => $replaced_content
            ]);
        } else {
            return new RedirectResponse($urlGenerator->generate('web_homepage'));
        }
    }

}
