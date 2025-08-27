<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Repository\Project\LabelRepository;
use Knp\Component\Pager\PaginatorInterface;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BlogController extends AbstractController
{
    #[Route(path: '/blog', name: 'web_blog_landing', defaults: ['slug' => null, 'page' => null], priority: 3)]
    #[Route(path: '/blog/stranka-{page}', name: 'web_blog_landing_paged', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['slug' => null, 'page' => null], priority: 2)]
    public function blogLandingPage($page, PaginatorInterface $paginator, CategoryRepository $categoryRepository, LabelRepository $labelRepository): Response
    {
        $blogLandingPage = $categoryRepository->findOneByHinted(['id' => 2]);
        $blogArticles    = $categoryRepository->findByHinted(['categoryType' => 6, 'isActive' => 1], ['id' => 'DESC']);
        $blogLabels      = $labelRepository->findBy(['labelType' => 3]);

        if ($page == null) {
            $page = 1;
        }

        $pagination = $paginator->paginate($blogArticles, $page, 10);
        $pagination->setTemplate('pagination/pagination_blog.html.twig');

        return $this->render('web/blog/landing.html.twig', [
            'category'     => $blogLandingPage,
            'blogArticles' => $blogArticles,
            'pagination'   => $pagination,
            'blogLabels'   => $blogLabels
        ]);
    }

    #[Route(path: '/blog/vse', name: 'web_blog_all', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null, 'slug' => null], priority: 2)]
    #[Route(path: '/blog/vse/stranka-{page}', name: 'web_blog_all_paged', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null, 'slug' => null], priority: 2)]
    #[Route(path: '/blog/{slug}-c', name: 'web_blog_filter', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null], priority: 2)]
    #[Route(path: '/blog/{slug}-c/stranka-{page}', requirements: ['slug' => '[A-Za-z0-9\-]+'], name: 'web_blog_filter_paged', priority: 2)]
    public function blogCategory($slug, $page, CategoryRepository $categoryRepository, PaginatorInterface $paginator, LabelRepository $labelRepository): Response
    {

        if ($slug == null) {
            $blogArticles = $categoryRepository->findByHinted(['categoryType' => 6, 'isActive' => 1], ['id' => 'DESC']);
            $title        = "Všechny články";
        } else {
            $selectedLabel = $labelRepository->findOneBy(['slug' => $slug]);
            $blogArticles  = $categoryRepository->findBlogCategoriesByLabel($selectedLabel);
            $title         = $selectedLabel->getName();
        }

        $blogLabels = $labelRepository->findBy(['labelType' => 3]);

        if ($page == null) {
            $page = 1;
        }

        $pagination = $paginator->paginate($blogArticles, $page, 10);
        $pagination->setTemplate('pagination/pagination_blog.html.twig');


        return $this->render('web/blog/category.html.twig', [
            'blogArticles' => $blogArticles,
            'pagination'   => $pagination,
            'labels'       => $blogLabels,
            'category'     => isset($selectedLabel) ? $selectedLabel : null,
            'title'        => $title
        ]);
    }

    #[Route(path: '/blog/{slug}', name: 'web_blog_detail', priority: 2)]
    public function blogDetail(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Category $category,
        LabelRepository $labelRepository,
        CategoryRepository $categoryRepository
    ): Response
    {

        $blogLabels     = $labelRepository->findBy(['labelType' => 3]);
        $latestArticles = $categoryRepository->findByHinted(['categoryType' => 6], ['id' => 'DESC'], 3);

        return $this->render('web/blog/detail.html.twig', [
            'category'       => $category,
            'labels'         => $blogLabels,
            'latestArticles' => $latestArticles
        ]);
    }
}
