<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Controller\WebController;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\ContactMessage;
use Greendot\EshopBundle\Form\ContactFormType;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\ReservedTimeRepository;
use Greendot\EshopBundle\Service\CategoryInfoGetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\Service\dynamicReplacement;
use Symfony\Component\Serializer\SerializerInterface;

class PageController extends AbstractController implements WebController
{
    #[Route(
        path: '/{slug}',
        name: 'web_get_page',
        options: ['expose' => true]
    )]
    public function getPage(
        Category               $category,
        CategoryRepository     $categoryRepository,
        UrlGeneratorInterface  $urlGenerator,
        Request                $request,
        EntityManagerInterface $entityManager,
        dynamicReplacement     $dynamicReplacement,
        CategoryInfoGetter     $categoryInfoGetter): Response
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
            $replaced_content = $dynamicReplacement->dynamicCategoryReplace($category->getHtml());
            return $this->render('web/pages/empty_page.html.twig', [
                'title'            => $title,
                'category'         => $category,
                'contact_form'     => $form->createView(),
                'replaced_content' => $replaced_content
            ]);
        } else {
            return new RedirectResponse($urlGenerator->generate('web_homepage'));
        }
    }

    #[Route(
        path: '/my-api/time-reservations',
        name: 'web_get_reservations',
    )]
    public function getReservations(ReservedTimeRepository $reservedTimeRepository, SerializerInterface $serializer): Response
    {
        $reservations = $reservedTimeRepository->findAll();
        $json         = $serializer->serialize($reservations, 'json');
        return new JsonResponse($json, 200, [], true);
    }
}
