<?php

namespace Greendot\EshopBundle\Service;

use App\Message\SitemapGenerateMessage;use Greendot\EshopBundle\Repository\Project\CategoryRepository;use Greendot\EshopBundle\Repository\Project\ProductRepository;use Symfony\Component\Messenger\MessageBusInterface;use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapGenerator
{
    public const MAX_PRODUCTS_ITEMS = 5000;
    private $categoryRepository;
    private $productRepository;
    private $bus;
    private $urlGenerator;

    public function __construct(
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        MessageBusInterface $bus,
        UrlGeneratorInterface $urlGenerator,
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->bus = $bus;
        $this->urlGenerator = $urlGenerator;
    }

    public function dispatchSitemaps(): void
    {
        $this->bus->dispatch(new SitemapGenerateMessage('category'));

        $productCount = $this->productRepository->countAll();
        $productSitemapCount = ceil($productCount / self::MAX_PRODUCTS_ITEMS);

        for ($i = 1; $i <= $productSitemapCount; $i++) {
            $this->bus->dispatch(new SitemapGenerateMessage('product', $i));
        }
    }

    public function generateSitemapIndex(): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

        $this->addSitemap($xml, $this->urlGenerator->generate('sitemap_category', [], UrlGeneratorInterface::ABSOLUTE_URL));

        $productCount = $this->productRepository->countAll();
        $productSitemapCount = ceil($productCount / self::MAX_PRODUCTS_ITEMS);

        for ($i = 1; $i <= $productSitemapCount; $i++) {
            $this->addSitemap($xml, $this->urlGenerator->generate('sitemap_product', ['page' => $i], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        return $xml->asXML();
    }

    public function generateProductSitemap(int $limit, int $offset): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        $baseUrl = $this->urlGenerator->generate('shop_product', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $products = $this->productRepository->findAllSlugsWithLimit($limit, $offset);
        foreach ($products as $product) {
            $url = $xml->addChild('url');
            $url->addChild('loc', $baseUrl . '/' . $product->getSlug());
        }

        return $xml->asXML();
    }

    public function generateCategorySitemap(): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        $baseUrl = $this->urlGenerator->generate('shop_category', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $categories = $this->categoryRepository->findAllHinted();
        foreach ($categories as $category) {
            $url = $xml->addChild('url');
            $url->addChild('loc', $baseUrl . $category->getSlug());
        }

        return $xml->asXML();
    }

    private function addSitemap(\SimpleXMLElement $xml, string $loc): void
    {
        $sitemap = $xml->addChild('sitemap');
        $sitemap->addChild('loc', $loc);
    }
}