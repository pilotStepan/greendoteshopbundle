<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\ApiRequestDetector;
use Greendot\EshopBundle\EventSubscriber\AnonymousClientLogoutListener;

/**
 * The anonymous client created at checkout is only allowed to see its order detail page;
 * on the next page it is logged out. The customer must land on the page they clicked,
 * not on the logout target (homepage).
 */
class AnonymousClientLogoutListenerTest extends TestCase
{
    private const PRODUCT_URL = 'https://shop.example.com/korkova-podlozka-na-jogu-lotos-p';

    public function testAnonymousClientIsLoggedOutAndSentToTheRequestedPage(): void
    {
        $event = $this->dispatch(Request::create(self::PRODUCT_URL), $this->anonymousClient());

        $response = ($event->getController())();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(self::PRODUCT_URL, $response->getTargetUrl());
    }

    public function testUnsafeRequestKeepsTheDefaultLogoutTarget(): void
    {
        $event = $this->dispatch(Request::create(self::PRODUCT_URL, 'POST'), $this->anonymousClient());

        $this->assertSame('/', ($event->getController())()->getTargetUrl());
    }

    public function testOrderDetailPageKeepsTheAnonymousSession(): void
    {
        $request = Request::create('https://shop.example.com/zakaznik/objednavka/1');
        $request->attributes->set('_route', 'client_section_order_detail');

        $event = $this->dispatch($request, $this->anonymousClient(), expectLogout: false);

        $this->assertSame('original', ($event->getController())());
    }

    public function testRegularClientIsNotLoggedOut(): void
    {
        $event = $this->dispatch(Request::create(self::PRODUCT_URL), (new Client())->setIsAnonymous(false), expectLogout: false);

        $this->assertSame('original', ($event->getController())());
    }

    private function dispatch(Request $request, Client $user, bool $expectLogout = true): ControllerEvent
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->expects($expectLogout ? $this->once() : $this->never())
            ->method('logout')
            ->with(false)
            ->willReturn(new RedirectResponse('/'));

        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn() => 'original',
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        (new AnonymousClientLogoutListener($security, new ListenerManager(), new ApiRequestDetector()))
            ->onKernelController($event);

        return $event;
    }

    private function anonymousClient(): Client
    {
        return (new Client())->setIsAnonymous(true);
    }
}
