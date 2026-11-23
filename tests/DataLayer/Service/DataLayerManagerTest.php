<?php

namespace Greendot\EshopBundle\Tests\DataLayer\Service;

use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class DataLayerManagerTest extends TestCase
{
    private DataLayerManager $manager;

    protected function setUp(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->manager = new DataLayerManager($requestStack);
    }

    public function testPersistedEcommerceEventGetsPersistedResetRightBeforeIt(): void
    {
        $this->manager->push(['event' => 'add_to_cart', 'ecommerce' => ['value' => 1]], true);

        self::assertSame(
            [['ecommerce' => null], ['event' => 'add_to_cart', 'ecommerce' => ['value' => 1]]],
            $this->manager->all(),
        );
    }

    public function testBareResetFromCallerIsIgnored(): void
    {
        $this->manager->push(['ecommerce' => null]);
        $this->manager->push(['event' => 'view_cart', 'ecommerce' => ['value' => 1]], true);

        self::assertCount(2, $this->manager->all());
    }

    public function testEventWithoutEcommerceGetsNoReset(): void
    {
        $this->manager->push(['event' => 'cookies_accepted'], true);

        self::assertSame([['event' => 'cookies_accepted']], $this->manager->all());
    }

    public function testAllDrainsTheSessionQueue(): void
    {
        $this->manager->push(['event' => 'view_item', 'ecommerce' => []], true);
        $this->manager->all();

        self::assertSame([], $this->manager->all());
    }
}
