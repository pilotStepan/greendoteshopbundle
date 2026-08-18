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
 * Registered automatically on the 'messenger.bus.default' bus via
 * config/extensions/messenger.yaml (see GreendotEshopBundle::prependExtension()).
 * If the consuming app named its bus differently, add it manually instead:
 *
 *   framework:
 *     messenger:
 *       buses:
 *         <your_bus_name>:
 *           middleware:
 *             - Greendot\EshopBundle\Messenger\Middleware\LocaleMiddleware
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
