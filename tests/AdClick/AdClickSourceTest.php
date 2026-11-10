<?php

namespace Greendot\EshopBundle\Tests\AdClick;

use Greendot\EshopBundle\AdClick\AdClickSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdClickSourceTest extends TestCase
{
    #[DataProvider('clickProvider')]
    public function testFromClick(array $params, ?string $referrer, AdClickSource $expected): void
    {
        $this->assertSame($expected, AdClickSource::fromClick($params, $referrer));
    }

    public static function clickProvider(): iterable
    {
        yield 'gclid' => [['gclid' => 'abc'], null, AdClickSource::GoogleAds];
        yield 'gbraid' => [['gbraid' => 'abc'], null, AdClickSource::GoogleAds];
        yield 'wbraid' => [['wbraid' => 'abc'], null, AdClickSource::GoogleAds];
        yield 'click id wins over utm' => [['gclid' => 'abc', 'utm_source' => 'heureka.cz'], null, AdClickSource::GoogleAds];
        yield 'msclkid' => [['msclkid' => 'abc'], null, AdClickSource::MicrosoftAds];
        yield 'ttclid' => [['ttclid' => 'abc'], null, AdClickSource::TikTok];
        yield 'fbclid' => [['fbclid' => 'abc'], null, AdClickSource::Meta];
        yield 'sznclid' => [['sznclid' => 'abc'], 'https://www.seznam.cz/', AdClickSource::Sklik];
        yield 'sznaiid' => [['sznaiid' => 'abc'], null, AdClickSource::Sklik];
        yield 'seznam click id from zbozi utm' => [['sznclid' => 'abc', 'utm_source' => 'zbozi.cz'], null, AdClickSource::Zbozi];
        yield 'seznam click id from zbozi referrer' => [['sznaiid' => 'abc'], 'https://www.zbozi.cz/', AdClickSource::Zbozi];
        yield 'heureka utm' => [['utm_source' => 'heureka.cz'], null, AdClickSource::Heureka];
        yield 'heureka utm uppercase' => [['utm_source' => 'Heureka'], null, AdClickSource::Heureka];
        yield 'heureka referrer only' => [['utm_campaign' => 'feed'], 'https://www.heureka.sk/', AdClickSource::Heureka];
        yield 'zbozi utm' => [['utm_source' => 'zbozi.cz'], null, AdClickSource::Zbozi];
        yield 'sklik utm' => [['utm_source' => 'sklik.cz'], null, AdClickSource::Sklik];
        yield 'google utm' => [['utm_source' => 'google', 'utm_medium' => 'cpc'], null, AdClickSource::GoogleAds];
        yield 'facebook utm' => [['utm_source' => 'facebook'], null, AdClickSource::Meta];
        yield 'instagram utm' => [['utm_source' => 'ig'], null, AdClickSource::Meta];
        yield 'bing utm' => [['utm_source' => 'bing'], null, AdClickSource::MicrosoftAds];
        yield 'tiktok utm' => [['utm_source' => 'tiktok'], null, AdClickSource::TikTok];
        yield 'newsletter' => [['utm_source' => 'newsletter', 'utm_medium' => 'email'], null, AdClickSource::Other];
    }
}
