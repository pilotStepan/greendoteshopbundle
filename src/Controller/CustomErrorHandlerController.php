<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CustomErrorHandlerController extends AbstractController
{
    #[Route("/error", name:"error_show")]
    public function error_show(CategoryRepository$categoryRepository, Request $request ,EntityManagerInterface $entityManager): Response
    {
        /*
         * k promene locale se neda dostat jinak,
         * klasicky getLocale vyhodnocuje celkovy locale pokud je locale null vrati defaultLocale atd
         *
         * pokud zkusim $request->get("locale") vrati null pokazde
        */
        $requestArray = (array)$request;

        $startedLocale = $requestArray["\x00*\x00locale"];
        if ($requestArray["\x00*\x00locale"] == null){
            $request->setLocale('cs');
        }

        $category = $categoryRepository->find(9);
        if ($startedLocale != $request->getLocale()){
            $category->setTranslatableLocale($request->getLocale());
            $entityManager->refresh($category);
        }
        return new Response($this->renderView('web/pages/empty_page.html.twig', [
            'title' => $category->getTitle(),
            'category' => $category,
            'replaced_content' => $category->getHtml(),
        ]), 404);
    }

}