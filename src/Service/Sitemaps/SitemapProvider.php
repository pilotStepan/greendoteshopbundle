<?php

namespace Greendot\EshopBundle\Service\Sitemaps;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class SitemapProvider
{
    /** @var SitemapProviderInterface[] */
    private iterable $sitemapProviderInterfaces;
    public function __construct(
        #[AutowireIterator('app.sitemap_provider')]
        iterable $sitemapProviders
    ){
        $this->sitemapProviderInterfaces = $sitemapProviders;
    }

    /**
     * @return SitemapProviderInterface[]
     */
    public function getAll(): iterable
    {
        return $this->sitemapProviderInterfaces;
    }

    public function get(string $name): ?SitemapProviderInterface
    {
        foreach ($this->sitemapProviderInterfaces as $sitemapProviderInterface) {
            if ($sitemapProviderInterface->name() === $name) return $sitemapProviderInterface;
        }
        return null;
    }
}