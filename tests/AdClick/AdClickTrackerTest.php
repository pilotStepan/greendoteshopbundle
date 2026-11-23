<?php

namespace Greendot\EshopBundle\Tests\AdClick;

use Greendot\EshopBundle\AdClick\AdClickTracker;
use Greendot\EshopBundle\Entity\Project\Purchase;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AdClickTrackerTest extends TestCase
{
    public function testReadClicksNormalizesCookieEntries(): void
    {
        $clickedAt = $this->daysAgo(2);
        $request = $this->requestWithCookie([[
            'clicked_at' => $clickedAt,
            'landing_path' => '/obleceni/mikiny',
            'referrer' => 'https://www.google.com/search?q=mikina',
            'params' => ['gclid' => 'Cj0K', 'utm_source' => 'google', 'color' => 'black'],
        ]]);

        $clicks = $this->tracker()->readClicks($request);

        $this->assertSame([[
            'clicked_at' => $clickedAt,
            'source' => 'google_ads',
            'landing_path' => '/obleceni/mikiny',
            'referrer' => 'https://www.google.com/',
            'params' => ['gclid' => 'Cj0K', 'utm_source' => 'google'],
        ]], $clicks);
    }

    public function testReadClicksSortsOldestFirst(): void
    {
        $request = $this->requestWithCookie([
            $this->click($this->daysAgo(1), ['gclid' => 'newer']),
            $this->click($this->daysAgo(5), ['utm_source' => 'heureka.cz']),
        ]);

        $clicks = $this->tracker()->readClicks($request);

        $this->assertSame(['heureka', 'google_ads'], array_column($clicks, 'source'));
    }

    public function testReadClicksDropsInvalidExpiredAndFutureEntries(): void
    {
        $request = $this->requestWithCookie([
            $this->click($this->daysAgo(31), ['gclid' => 'expired']),
            $this->click((new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i:s\Z'), ['gclid' => 'future']),
            $this->click('tomorrow', ['gclid' => 'not-iso']),
            $this->click($this->daysAgo(1), ['unknown' => 'x']),
            'not-an-object',
            ['clicked_at' => $this->daysAgo(1)],
            $this->click($this->daysAgo(1), ['gclid' => 'valid']),
        ]);

        $clicks = $this->tracker()->readClicks($request);

        $this->assertCount(1, $clicks);
        $this->assertSame('valid', $clicks[0]['params']['gclid']);
    }

    public function testReadClicksKeepsOnlyTheNewestTen(): void
    {
        $entries = [];
        for ($i = 12; $i >= 1; $i--) {
            $entries[] = $this->click($this->daysAgo($i), ['gclid' => "click-$i"]);
        }

        $clicks = $this->tracker()->readClicks($this->requestWithCookie($entries));

        $this->assertCount(10, $clicks);
        $this->assertSame('click-10', $clicks[0]['params']['gclid']);
        $this->assertSame('click-1', $clicks[9]['params']['gclid']);
    }

    public function testReadClicksTruncatesLongValuesAndRejectsBadPathsAndReferrers(): void
    {
        $request = $this->requestWithCookie([[
            'clicked_at' => $this->daysAgo(1),
            'landing_path' => 'https://evil.example/',
            'referrer' => 'javascript:alert(1)',
            'params' => ['gclid' => str_repeat('a', 400)],
        ]]);

        $click = $this->tracker()->readClicks($request)[0];

        $this->assertSame(255, mb_strlen($click['params']['gclid']));
        $this->assertNull($click['landing_path']);
        $this->assertNull($click['referrer']);
    }

    public function testReadClicksIgnoresMalformedCookie(): void
    {
        $request = Request::create('/', cookies: [AdClickTracker::COOKIE => '{not json']);

        $this->assertSame([], $this->tracker()->readClicks($request));
    }

    public function testAssignToPurchaseCopiesCookieAndKeepsOtherInfo(): void
    {
        $request = $this->requestWithCookie([$this->click($this->daysAgo(1), ['sznclid' => 'abc'])]);
        $purchase = (new Purchase())->setShipmentId('123');

        $this->tracker($request)->assignToPurchase($purchase);

        $this->assertSame('sklik', $purchase->getAdClicks()[0]['source']);
        $this->assertSame('123', $purchase->getShipmentId());
    }

    public function testAssignToPurchaseWithoutCookieRemovesClicks(): void
    {
        $purchase = (new Purchase())->setAdClicks([['source' => 'meta']]);

        $this->tracker(Request::create('/'))->assignToPurchase($purchase);

        $this->assertSame([], $purchase->getAdClicks());
        $this->assertArrayNotHasKey('ad_clicks', $purchase->getAdditionalInfo());
    }

    public function testAssignToPurchaseWithoutRequestLeavesPurchaseUntouched(): void
    {
        $purchase = (new Purchase())->setAdClicks([['source' => 'meta']]);

        $this->tracker()->assignToPurchase($purchase);

        $this->assertSame([['source' => 'meta']], $purchase->getAdClicks());
    }

    public function testAssignToOrderClearsCookieOnMainResponse(): void
    {
        $request = $this->requestWithCookie([$this->click($this->daysAgo(1), ['gclid' => 'abc'])]);
        $tracker = $this->tracker($request);
        $purchase = new Purchase();

        $tracker->assignToOrder($purchase);
        $event = $this->responseEvent($request);
        $tracker->clearCookieAfterCheckout($event);

        $this->assertCount(1, $purchase->getAdClicks());
        $cookie = $this->findCookie($event->getResponse());
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isCleared());
    }

    public function testCookieIsNotClearedWithoutCheckout(): void
    {
        $request = $this->requestWithCookie([$this->click($this->daysAgo(1), ['gclid' => 'abc'])]);
        $event = $this->responseEvent($request);

        $this->tracker($request)->clearCookieAfterCheckout($event);

        $this->assertNull($this->findCookie($event->getResponse()));
    }

    private function tracker(?Request $request = null): AdClickTracker
    {
        $requestStack = new RequestStack();
        if ($request) {
            $requestStack->push($request);
        }

        return new AdClickTracker($requestStack, new NullLogger());
    }

    private function requestWithCookie(array $entries): Request
    {
        return Request::create('/', cookies: [AdClickTracker::COOKIE => json_encode($entries)]);
    }

    private function click(string $clickedAt, array $params): array
    {
        return ['clicked_at' => $clickedAt, 'landing_path' => '/', 'referrer' => null, 'params' => $params];
    }

    private function daysAgo(int $days): string
    {
        return (new \DateTimeImmutable("-$days days", new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    private function responseEvent(Request $request): ResponseEvent
    {
        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );
    }

    private function findCookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === AdClickTracker::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }
}
