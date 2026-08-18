<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the locale a message should be handled under.
 *
 * @see \Greendot\EshopBundle\Messenger\Middleware\LocaleMiddleware
 */
final readonly class LocaleStamp implements StampInterface
{
    public function __construct(public string $locale) {}
}
