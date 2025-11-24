<?php

namespace Greendot\EshopBundle\Controller\Web;

use Greendot\EshopBundle\Attribute\TranslatableRoute;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Repository\Project\LabelRepository;
use Knp\Component\Pager\PaginatorInterface;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route(path: '/%greendot_eshop.blog.slug%', defaults: ['blogSlug' => 'blog'])]
class BlogController extends AbstractController
{
    #[Route(path: '/', name: 'web_blog_landing', defaults: ['page' => null], priority: 3)]
    #[Route(path: '/stranka-{page}', name: 'web_blog_landing_paged', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null], priority: 2)]
    public function blogLandingPage(
        ?int $page,
        PaginatorInterface $paginator,
        CategoryRepository $categoryRepository,
        LabelRepository $labelRepository,
        ParameterBagInterface $parameterBag
    ): Response
    {
        $blogLandingPage = $categoryRepository->findOneByHinted(['id' => 2]);

        $hasLanding = $parameterBag->get('greendot_eshop.blog.has_landing');
        if (!$hasLanding){
            return $this->redirectToRoute('web_blog_all');
        }

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

    #[TranslatableRoute(class: Category::class, property: 'slug')]
    #[Route(path: '/vse', name: 'web_blog_all', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null, 'slug' => null], priority: 2)]
    #[Route(path: '/vse/stranka-{page}', name: 'web_blog_all_paged', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null, 'slug' => null], priority: 2)]
    #[Route(path: '/{slug}-c', name: 'web_blog_filter', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['page' => null], priority: 2)]
    #[Route(path: '/{slug}-c/stranka-{page}', requirements: ['slug' => '[A-Za-z0-9\-]+'], name: 'web_blog_filter_paged', priority: 2)]
    public function blogCategory(?string $slug, $page, CategoryRepository $categoryRepository, PaginatorInterface $paginator, LabelRepository $labelRepository): Response
    {
        $blogLandingPage = $categoryRepository->findOneByHinted(['id' => 2]);
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
            'blogLanding' => $blogLandingPage,
            'blogArticles' => $blogArticles,
            'pagination'   => $pagination,
            'labels'       => $blogLabels,
            'category'     => isset($selectedLabel) ? $selectedLabel : null,
            'title'        => $title
        ]);
    }

    #[TranslatableRoute(class: Category::class, property: 'slug')]
    #[Route(path: '/{slug}', name: 'web_blog_detail', priority: 2)]
    public function blogDetail(
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
