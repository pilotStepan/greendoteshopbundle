<?php

namespace Greendot\EshopBundle\AdClick;

/**
 * Readable label for a stored ad click. Click IDs win over utm_source, which wins over the referrer host.
 */
enum AdClickSource: string
{
    case GoogleAds = 'google_ads';
    case Sklik = 'sklik';
    case Zbozi = 'zbozi';
    case Heureka = 'heureka';
    case Meta = 'meta';
    case MicrosoftAds = 'microsoft_ads';
    case TikTok = 'tiktok';
    case Other = 'other';

    public static function fromClick(array $params, ?string $referrer): self
    {
        $utmSource = strtolower($params['utm_source'] ?? '');
        $referrerHost = strtolower((string)parse_url($referrer ?? '', PHP_URL_HOST));
        $mentions = static fn(string $needle) => str_contains($utmSource, $needle) || str_contains($referrerHost, $needle);

        return match (true) {
            isset($params['gclid']) || isset($params['gbraid']) || isset($params['wbraid']) => self::GoogleAds,
            isset($params['msclkid']) => self::MicrosoftAds,
            isset($params['ttclid']) => self::TikTok,
            // Zboží.cz ads run through Sklik and carry the same Seznam click IDs
            isset($params['sznclid']) || isset($params['sznaiid']) => $mentions('zbozi') ? self::Zbozi : self::Sklik,
            isset($params['fbclid']) => self::Meta,
            $mentions('heureka') => self::Heureka,
            $mentions('zbozi') => self::Zbozi,
            str_contains($utmSource, 'sklik') || str_contains($utmSource, 'seznam') => self::Sklik,
            str_contains($utmSource, 'google') => self::GoogleAds,
            str_contains($utmSource, 'facebook') || str_contains($utmSource, 'instagram'),
            in_array($utmSource, ['fb', 'ig', 'meta'], true) => self::Meta,
            str_contains($utmSource, 'bing') || str_contains($utmSource, 'microsoft') => self::MicrosoftAds,
            str_contains($utmSource, 'tiktok') => self::TikTok,
            default => self::Other,
        };
    }
}
