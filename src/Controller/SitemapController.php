<?php

namespace Greendot\EshopBundle\Controller;


use Greendot\EshopBundle\Service\Sitemaps\SitemapProvider;
use Greendot\EshopBundle\Service\Sitemaps\SitemapTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sitemap', name: 'sitemap_')]
class SitemapController extends AbstractController
{
    use SitemapTrait;

    public function __construct(
        private readonly SitemapProvider $sitemapProvider
    ){}

    #[Route('.xml', name: 'index', priority: 999)]
    public function index(): Response
    {
        $xml = $this->blankSitemapIndex();
        foreach ($this->sitemapProvider->getAll() as $sitemapProvider){
            $sitemapProvider->addToSitemapIndex($xml);
        }
        return $this->generateXmlResponse($xml);
    }

    #[Route('/sitemap-category.xml', name: 'category')]
    public function category(): Response
    {
        $provider = $this->sitemapProvider->get('category');
        return $provider->generateSiteMap();
    }

    #[Route('/product_{page}.xml', name: 'product')]
    public function product(int $page): Response
    {
        $provider = $this->sitemapProvider->get('product');
        return $provider->generateSiteMap(['page' => $page]);
    }
}