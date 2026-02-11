<?php

namespace Greendot\EshopBundle\Service\Sitemaps;

use Doctrine\ORM\QueryBuilder;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductSitemap implements SitemapProviderInterface
{
    use SitemapTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ProductRepository $productRepository
    ){}
    public const MAX_PRODUCTS_ITEMS = 5000;

    public function name(): string
    {
        return 'product';
    }

    public function addToSitemapIndex(\SimpleXMLElement $xmlUrlSet): void
    {
        $productCount = $this->sitemapProductQB()->select('count(product.id)')->getQuery()->getSingleScalarResult();
        for($i = 0; $i< $productCount; $i+= self::MAX_PRODUCTS_ITEMS){
            $page = $i / self::MAX_PRODUCTS_ITEMS;

            $this->addToIndex($xmlUrlSet, $this->urlGenerator->generate('sitemap_product', ['page'=> $page], UrlGeneratorInterface::ABSOLUTE_URL));
        }
    }

    public function generateSiteMap(array $options = []): Response
    {
        [ 'page' => $page ] = $options;

        $xml = $this->blankUrlSet();
        $productQb = $this->sitemapProductQB();
        $products = $productQb
            ->select('product.slug as slug')
            ->setMaxResults(self::MAX_PRODUCTS_ITEMS)
            ->setFirstResult($page * self::MAX_PRODUCTS_ITEMS)
            ->getQuery()->getArrayResult()
        ;
        $products = array_column($products, 'slug');
        foreach ($products as $productSlug){
            $this->addToUrlSet($xml, $this->urlGenerator->generate('shop_product', ['slug' => $productSlug], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        return $this->generateXmlResponse($xml);
    }

    private function sitemapProductQB(): QueryBuilder
    {
        return $this->productRepository->createQueryBuilder('product')
            ->andWhere('product.isActive = 1')
            ->andWhere('product.isVisible = 1')
            ->andWhere('product.isIndexable = 1');
    }
}