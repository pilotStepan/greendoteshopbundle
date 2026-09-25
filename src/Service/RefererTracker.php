<?php

namespace Greendot\EshopBundle\Service;

use Psr\Log\LoggerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;


class RefererTracker
{
    public const REFERER_COOKIE = 'referer';

    private const COOKIE_LIFETIME = 2592000;
    private const MAX_LENGTH = 1000;

    public function __construct(
        private RequestStack    $requestStack,
        private LoggerInterface $logger,
    ) {}

    public function captureRefererCookie(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->cookies->has(self::REFERER_COOKIE)) {
            return;
        }

        $referer = $request->headers->get('referer');

        if (!$referer || !$this->isExternal($referer, $request)) {
            return;
        }

        $this->logger->info('Referer: capturing external referer to cookie.', [
            'referer' => $referer,
        ]);

        $event->getResponse()->headers->setCookie(
            new Cookie(
                self::REFERER_COOKIE,
                mb_substr($referer, 0, self::MAX_LENGTH),
                time() + self::COOKIE_LIFETIME,
                '/',
                null,
                false,
                true,
            ),
        );
    }

    public function setRefererToPurchase(Purchase $purchase): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $purchase->setReferer($request?->cookies->get(self::REFERER_COOKIE));
    }

    private function isExternal(string $referer, Request $request): bool
    {
        $refererHost = parse_url($referer, PHP_URL_HOST);

        if (!$refererHost) {
            return false;
        }

        return $this->normalizeHost($refererHost) !== $this->normalizeHost($request->getHost());
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
