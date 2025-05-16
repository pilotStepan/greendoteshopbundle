<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdviceController extends AbstractController
{
    #[Route('/{slug}', name: 'app_advice')]
    public function index(Category $category, CommentRepository $commentRepository): Response
    {
//        $comments = $commentRepository->findAll();


        return $this->render('advice/index.html.twig', [
            'category' => $category,
//            'comments' => $comments,
        ]);
    }

    #[Route('poradna/{slug}', name: 'app_comment_detail')]
    public function commentDetail(CommentRepository $commentRepository, $slug): Response
    {
        $comment = $commentRepository->findOneBy(['slug'=>$slug]);


        return $this->render('advice/comment-detail.html.twig', [
            $comment
        ]);
    }


}
