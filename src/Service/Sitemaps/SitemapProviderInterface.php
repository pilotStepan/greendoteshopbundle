<?php

namespace Greendot\EshopBundle\Service\Sitemaps;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag('app.sitemap_provider')]
interface SitemapProviderInterface
{
    public function name(): string;

    public function addToSitemapIndex(\SimpleXMLElement $xmlUrlSet): void;

    public function generateSiteMap(array $options = []): Response;


}