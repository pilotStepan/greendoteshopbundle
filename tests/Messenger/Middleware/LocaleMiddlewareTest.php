<?php

namespace Greendot\EshopBundle\Tests\Messenger\Middleware;

use Greendot\EshopBundle\Messenger\Middleware\LocaleMiddleware;
use Greendot\EshopBundle\Messenger\Stamp\LocaleStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class LocaleMiddlewareTest extends TestCase
{
    public function testDispatchSideAttachesLocaleStampWhenAbsent(): void
    {
        $localeSwitcher = new LocaleSwitcher('cs', []);
        $middleware = new LocaleMiddleware($localeSwitcher);

        $envelope = new Envelope(new \stdClass());

        $stack = $this->createStackReturning(function (Envelope $received) {
            $this->assertNotNull($received->last(LocaleStamp::class));
            $this->assertSame('cs', $received->last(LocaleStamp::class)->locale);

            return $received;
        });

        $middleware->handle($envelope, $stack);
    }

    public function testDispatchSideDoesNotOverrideExistingLocaleStamp(): void
    {
        $localeSwitcher = new LocaleSwitcher('cs', []);
        $middleware = new LocaleMiddleware($localeSwitcher);

        $envelope = (new Envelope(new \stdClass()))->with(new LocaleStamp('sk'));

        $stack = $this->createStackReturning(function (Envelope $received) {
            $this->assertSame('sk', $received->last(LocaleStamp::class)->locale);

            return $received;
        });

        $middleware->handle($envelope, $stack);
    }

    public function testReceiveSideRunsHandlingUnderStampedLocaleAndRestoresAfter(): void
    {
        $spy = new class implements LocaleAwareInterface {
            public array $seenLocales = [];
            private string $locale = 'cs';

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
                $this->seenLocales[] = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };

        $localeSwitcher = new LocaleSwitcher('cs', [$spy]);
        $middleware = new LocaleMiddleware($localeSwitcher);

        $envelope = (new Envelope(new \stdClass(), [new ReceivedStamp('async')]))
            ->with(new LocaleStamp('sk'));

        $localeDuringHandling = null;
        $stack = $this->createStackReturning(function (Envelope $received) use (&$localeDuringHandling, $localeSwitcher) {
            $localeDuringHandling = $localeSwitcher->getLocale();

            return $received;
        });

        $middleware->handle($envelope, $stack);

        $this->assertSame('sk', $localeDuringHandling);
        $this->assertSame('cs', $localeSwitcher->getLocale(), 'locale must be restored after handling');
        $this->assertSame('sk', $spy->seenLocales[0]);
        $this->assertSame('cs', $spy->seenLocales[1]);
    }

    private function createStackReturning(callable $callback): StackInterface
    {
        $middleware = new class($callback) implements \Symfony\Component\Messenger\Middleware\MiddlewareInterface {
            public function __construct(private $callback) {}

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return ($this->callback)($envelope);
            }
        };

        return new class($middleware) implements StackInterface {
            public function __construct(private $middleware) {}

            public function next(): \Symfony\Component\Messenger\Middleware\MiddlewareInterface
            {
                return $this->middleware;
            }
        };
    }
}
