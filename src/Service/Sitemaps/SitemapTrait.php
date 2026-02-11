<?php

namespace Greendot\EshopBundle\Service\Sitemaps;

use Symfony\Component\HttpFoundation\Response;

trait SitemapTrait
{
    private function blankUrlSet(): \SimpleXMLElement
    {
        return new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
    }

    private function addToUrlSet(\SimpleXMLElement $xml, string $loc): void
    {
        $sitemap = $xml->addChild('url');
        $sitemap->addChild('loc', $loc);
    }

    private function blankSitemapIndex(): \SimpleXMLElement
    {
        return new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
    }

    private function addToIndex(\SimpleXMLElement $xml, string $loc): void
    {
        $sitemap = $xml->addChild('sitemap');
        $sitemap->addChild('loc', $loc);
    }

    private function generateXmlResponse(\SimpleXMLElement $simpleXMLElement): Response
    {
        return new Response(
            $simpleXMLElement->asXML(),
            200,
            ['Content-Type' => 'text/xml; charset=utf-8']
        );
    }
}