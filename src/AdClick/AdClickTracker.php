<?php

namespace Greendot\EshopBundle\AdClick;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Copies ad clicks from the `ad_clicks` cookie onto carts and orders.
 *
 * The cookie is written in the browser by templates/ad_click/ad_click_capture.html.twig, and only once the visitor
 * has granted marketing consent, so its presence implies consent. The cookie is client-controlled, hence every entry
 * is re-validated here.
 */
class AdClickTracker
{
    public const COOKIE = 'ad_clicks';

    public const TRACKED_PARAMS = [
        'gclid', 'gbraid', 'wbraid',
        'sznclid', 'sznaiid',
        'fbclid', 'msclkid', 'ttclid',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    ];

    private const MAX_AGE_DAYS = 30;
    private const MAX_CLICKS = 10;
    private const MAX_LENGTH = 255;
    private const CLEAR_COOKIE_ATTRIBUTE = '_ad_clicks_clear_cookie';

    public function __construct(
        private RequestStack    $requestStack,
        private LoggerInterface $logger,
    ) {}

    /**
     * Without a request (CLI, messenger) the purchase is left as is; without a cookie its clicks are removed.
     */
    public function assignToPurchase(Purchase $purchase): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $purchase->setAdClicks($this->readClicks($request));
    }

    /**
     * Final copy at checkout. The cookie is then cleared so the next order only lists clicks made after this one.
     */
    public function assignToOrder(Purchase $purchase): void
    {
        $this->assignToPurchase($purchase);
        $this->requestStack->getMainRequest()?->attributes->set(self::CLEAR_COOKIE_ATTRIBUTE, true);
    }

    public function clearCookieAfterCheckout(ResponseEvent $event): void
    {
        if (!$event->getRequest()->attributes->get(self::CLEAR_COOKIE_ATTRIBUTE)) {
            return;
        }

        $event->getResponse()->headers->clearCookie(self::COOKIE, '/', null, false, false, 'lax');
    }

    /**
     * @return list<array{clicked_at: string, source: string, landing_path: ?string, referrer: ?string, params: array<string, string>}>
     */
    public function readClicks(Request $request): array
    {
        $raw = $request->cookies->get(self::COOKIE);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $entries = json_decode($raw, true);
        if (!is_array($entries) || !array_is_list($entries)) {
            $this->logger->warning('AdClick: malformed ad_clicks cookie ignored.', ['cookie' => mb_substr($raw, 0, 200)]);
            return [];
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $oldest = $now->modify(sprintf('-%d days', self::MAX_AGE_DAYS));
        // allow for a visitor's clock running ahead
        $latest = $now->modify('+1 day');

        $clicks = [];
        foreach ($entries as $entry) {
            $click = $this->normalizeClick($entry, $oldest, $latest);
            if ($click !== null) {
                $clicks[] = $click;
            }
        }

        usort($clicks, static fn(array $a, array $b) => $a['clicked_at'] <=> $b['clicked_at']);

        return array_slice($clicks, -self::MAX_CLICKS);
    }

    private function normalizeClick(mixed $entry, \DateTimeImmutable $oldest, \DateTimeImmutable $latest): ?array
    {
        if (!is_array($entry) || !is_array($entry['params'] ?? null)) {
            return null;
        }

        $params = [];
        foreach (self::TRACKED_PARAMS as $name) {
            $value = $entry['params'][$name] ?? null;
            if (is_string($value) && $value !== '') {
                $params[$name] = mb_substr($value, 0, self::MAX_LENGTH);
            }
        }

        $clickedAt = $this->parseTimestamp($entry['clicked_at'] ?? null);
        if (!$params || !$clickedAt || $clickedAt < $oldest || $clickedAt > $latest) {
            return null;
        }

        $landingPath = $entry['landing_path'] ?? null;
        $referrer = $this->normalizeReferrer($entry['referrer'] ?? null);

        return [
            'clicked_at' => $clickedAt->format('Y-m-d\TH:i:s\Z'),
            'source' => AdClickSource::fromClick($params, $referrer)->value,
            'landing_path' => is_string($landingPath) && str_starts_with($landingPath, '/') && !str_starts_with($landingPath, '//')
                ? mb_substr($landingPath, 0, self::MAX_LENGTH)
                : null,
            'referrer' => $referrer,
            'params' => $params,
        ];
    }

    private function parseTimestamp(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Only the origin is kept: enough to recognise the site, without search terms or other query data.
     */
    private function normalizeReferrer(mixed $referrer): ?string
    {
        if (!is_string($referrer)) {
            return null;
        }

        $scheme = parse_url($referrer, PHP_URL_SCHEME);
        $host = parse_url($referrer, PHP_URL_HOST);
        if (!in_array($scheme, ['http', 'https'], true) || !$host) {
            return null;
        }

        return sprintf('%s://%s/', $scheme, strtolower($host));
    }
}
