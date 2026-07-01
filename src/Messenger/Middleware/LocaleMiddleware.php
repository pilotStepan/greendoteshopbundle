<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Greendot\EshopBundle\Messenger\Stamp\LocaleStamp;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * Propagates the current locale across the async boundary.
 *
 * Must be registered on the bus in the consuming application's
 * config/packages/messenger.yaml.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(private LocaleSwitcher $localeSwitcher) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $isReceived = $envelope->last(ReceivedStamp::class) !== null;

        if (!$isReceived) {
            if ($envelope->last(LocaleStamp::class) === null) {
                $envelope = $envelope->with(new LocaleStamp($this->localeSwitcher->getLocale()));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        $stamp = $envelope->last(LocaleStamp::class);

        if (!$stamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $this->localeSwitcher->runWithLocale(
            $stamp->locale,
            fn() => $stack->next()->handle($envelope, $stack),
        );
    }
}
