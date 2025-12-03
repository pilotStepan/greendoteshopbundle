<?php

namespace Greendot\EshopBundle\Utils;

use Symfony\Component\HttpFoundation\Request;

final class ApiRequestMatcher
{
    /** @var string[] */
    private static array $prefixes = [
        '/shop/api',
        '/api',
        '/simple/api',
        '/my-api',
        '/translate',
        '/_fragment',
    ];

    public static function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach (self::$prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}