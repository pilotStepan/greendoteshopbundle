<?php

namespace Greendot\EshopBundle\I18n;

use Symfony\Component\Routing\RouterInterface;
use Greendot\EshopBundle\Utils\ApiRequestMatcher;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Sets the locale for API requests based on the referer URL
 * Register it as a listener to the kernel.request event:
 *
 * Greendot\EshopBundle\I18n\ApiLocaleListener:
 *   arguments:
 *     $defaultLocale: '%kernel.default_locale%'
 *   tags:
 *     -   name: kernel.event_listener
 *         event: kernel.request
 *         method: onKernelRequest
 *         priority: 20
 */
readonly class ApiLocaleListener
{
    public function __construct(
        private string          $defaultLocale,
        private RouterInterface $router,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!ApiRequestMatcher::isApiRequest($request)) {
            return;
        }

        $referer = $request->headers->get('referer');
        $locale = $this->determineLocaleFromReferer($referer);

        if ($locale) {
            $request->setLocale($locale);
        }
    }

    private function determineLocaleFromReferer(?string $referer): ?string
    {
        if (!$referer) return $this->defaultLocale;

        try {
            $path = parse_url($referer, PHP_URL_PATH);
            $parameters = $this->router->match($path);

            return $parameters['_locale'] ?? $this->defaultLocale;

        } catch (\Exception $e) {
            return $this->defaultLocale;
        }
    }
}