<?php

namespace Greendot\EshopBundle\Utils;

class ViesAddressParser
{
    public const COUNTRY_CZ = 'CZ';
    public const COUNTRY_SK = 'SK';

    /**
     * @param string|null $rawAddress  The multi-line address string from VIES (or null)
     * @param string      $countryCode Two-letter ISO country code (e.g. 'CZ', 'SK')
     * @return array{street: ?string, city: ?string, zip: ?string}
     */
    public static function parse(?string $rawAddress, string $countryCode): array
    {
        if (empty($rawAddress)) {
            return ['street' => null, 'city' => null, 'zip' => null];
        }

        // split on any newline, trim each line, collapse interior whitespace, drop empties
        $lines = preg_split('/\R+/u', trim($rawAddress));
        $lines = array_map(function (string $l): string {
            return trim(preg_replace('/\s+/u', ' ', $l));
        }, $lines);
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));

        // 2) if > 2 lines and last line has no digits, assume it's the country name -> drop it
        if (count($lines) > 2 && !preg_match('/\d/u', end($lines))) {
            array_pop($lines);
        }

        return match (strtoupper($countryCode)) {
            self::COUNTRY_SK => self::parseSK($lines),
            self::COUNTRY_CZ => self::parseCZ($lines),
            default          => self::parseGeneric($lines),
        };
    }

    private static function parseSK(array $lines): array
    {
        // SK format: [street], [ZIP City]
        $street = $lines[0] ?? null;
        $zip = null;
        $city = null;

        $last = end($lines) ?: '';
        if (preg_match('/^(\d{3}\s?\d{2})\s+(.+)$/u', $last, $m)) {
            $zip = $m[1];
            $city = $m[2];
        }

        return ['street' => $street, 'city' => $city, 'zip' => $zip];
    }

    private static function parseCZ(array $lines): array
    {
        // CZ format: [street], [city], [ZIP City]
        $street = $lines[0] ?? null;
        $city = $lines[1] ?? null;
        $zip = null;

        $last = end($lines) ?: '';
        if (preg_match('/^(\d{3}\s?\d{2})\s+(.+)$/u', $last, $m)) {
            $zip = $m[1];
            // keep $city from the second line (it often carries district info)
        }

        return ['street' => $street, 'city' => $city, 'zip' => trim($zip)];
    }

    private static function parseGeneric(array $lines): array
    {
        // fallback: same strategy as before but for unknown countries
        $street = $lines[0] ?? null;
        $zip = null;
        $city = null;
        $last = end($lines) ?: '';

        if (preg_match('/^(\d{3,5}\s?\d{2,5})\s+(.+)$/u', $last, $m)) {
            $zip = $m[1];
            if (count($lines) === 2) {
                $city = $m[2];
            } else {
                $city = $lines[1] ?? null;
            }
            return compact('street', 'city', 'zip');
        }

        // maybe it’s on the second line?
        if (isset($lines[1])
            && preg_match('/^(\d{3,5}\s?\d{2,5})\s+(.+)$/u', $lines[1], $m2)
        ) {
            $zip = $m2[1];
            $city = $m2[2];
            return compact('street', 'city', 'zip');
        }

        // give up -> line 2 as city
        $city = $lines[1] ?? null;
        return compact('street', 'city', 'zip');
    }
}