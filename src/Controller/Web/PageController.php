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
        UrlGeneratorInterface  $urlGenerator,
        ): Response
    {
        if ($category !== null) {
            if ($category->getTitle() === null || $category->getTitle() === '') {
                $title = $category->getName();
            } else {
                $title = $category->getTitle();
            }
            return $this->render('web/pages/empty_page.html.twig', [
                'title'            => $title,
                'category'         => $category,
                'replaced_content' => $category->getHtml()
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
