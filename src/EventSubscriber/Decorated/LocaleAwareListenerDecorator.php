<?php

namespace Greendot\EshopBundle\EventSubscriber\Decorated;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\LocaleAwareListener as BaseLocaleAwareListener;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsDecorator(decorates: 'locale_aware_listener')]
class LocaleAwareListenerDecorator implements EventSubscriberInterface
{
    private BaseLocaleAwareListener $inner;

    public function __construct(BaseLocaleAwareListener $awareListener)
    {
        $this->inner = $awareListener;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->isStateless($event)) return;
        $this->inner->onKernelRequest($event);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if ($this->isStateless($event)) return;
        $this->inner->onKernelFinishRequest($event);
    }

    private function isStateless(RequestEvent|FinishRequestEvent $event): bool
    {
        $apiPlatformStateless = $event->getRequest()?->attributes?->get('_stateless') ?? false;
        return $apiPlatformStateless;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must be registered after the Locale listener
            KernelEvents::REQUEST => [['onKernelRequest', 15]],
            KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', -15]],
        ];
    }
}