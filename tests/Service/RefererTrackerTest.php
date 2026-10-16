<?php

namespace Greendot\EshopBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\RefererTracker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RefererTrackerTest extends TestCase
{
    public function testCapturesExternalRefererAsCookie(): void
    {
        $tracker = new RefererTracker($this->createMock(RequestStack::class), new NullLogger());

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'yogashop.cz']);
        $request->headers->set('referer', 'https://www.seznam.cz/search?q=yoga');
        $event = $this->responseEvent($request);

        $tracker->captureRefererCookie($event);

        $cookie = $this->findCookie($event->getResponse(), RefererTracker::REFERER_COOKIE);
        $this->assertNotNull($cookie);
        $this->assertSame('https://www.seznam.cz/search?q=yoga', $cookie->getValue());
        // ~30 days, allow a little slack for test execution time
        $this->assertEqualsWithDelta(time() + 2592000, $cookie->getExpiresTime(), 5);
    }

    public function testIgnoresSameHostReferer(): void
    {
        $tracker = new RefererTracker($this->createMock(RequestStack::class), new NullLogger());

        $request = Request::create('/checkout', 'GET', [], [], [], ['HTTP_HOST' => 'yogashop.cz']);
        $request->headers->set('referer', 'https://www.yogashop.cz/cart');
        $event = $this->responseEvent($request);

        $tracker->captureRefererCookie($event);

        $this->assertNull($this->findCookie($event->getResponse(), RefererTracker::REFERER_COOKIE));
    }

    public function testIgnoresMissingReferer(): void
    {
        $tracker = new RefererTracker($this->createMock(RequestStack::class), new NullLogger());

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'yogashop.cz']);
        $event = $this->responseEvent($request);

        $tracker->captureRefererCookie($event);

        $this->assertNull($this->findCookie($event->getResponse(), RefererTracker::REFERER_COOKIE));
    }

    public function testFirstTouchDoesNotOverwriteExistingCookie(): void
    {
        $tracker = new RefererTracker($this->createMock(RequestStack::class), new NullLogger());

        $request = Request::create('/', 'GET', [], ['referer' => 'https://www.seznam.cz/'], [], ['HTTP_HOST' => 'yogashop.cz']);
        $request->headers->set('referer', 'https://www.google.com/');
        $event = $this->responseEvent($request);

        $tracker->captureRefererCookie($event);

        $this->assertNull($this->findCookie($event->getResponse(), RefererTracker::REFERER_COOKIE));
    }

    public function testSetRefererToPurchaseReadsFromRequestCookie(): void
    {
        $request = Request::create('/', 'GET', [], ['referer' => 'https://www.seznam.cz/']);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $tracker = new RefererTracker($requestStack, new NullLogger());

        $purchase = new Purchase();
        $tracker->setRefererToPurchase($purchase);

        $this->assertSame('https://www.seznam.cz/', $purchase->getReferer());
    }

    public function testSetRefererToPurchaseWithNoCookieClearsReferer(): void
    {
        $request = Request::create('/');

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $tracker = new RefererTracker($requestStack, new NullLogger());

        $purchase = new Purchase();
        $tracker->setRefererToPurchase($purchase);

        $this->assertNull($purchase->getReferer());
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

    private function findCookie(Response $response, string $name): ?\Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
