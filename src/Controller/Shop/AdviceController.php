<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Repository\Project\CommentRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdviceController extends AbstractController
{
    #[Route('/{slug}', name: 'app_advice')]
    public function index(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Category $category,
    ): Response
    {
        return $this->render('advice/index.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('poradna/{slug}', name: 'app_comment_detail')]
    public function commentDetail(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Comment $comment
    ): Response
    {
        return $this->render('advice/comment-detail.html.twig', [
            "comment" => $comment
        ]);
    }


}
