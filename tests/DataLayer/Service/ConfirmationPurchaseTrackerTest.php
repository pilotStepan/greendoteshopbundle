<?php

namespace Greendot\EshopBundle\Tests\DataLayer\Service;

use Greendot\EshopBundle\DataLayer\Data\Purchase\Purchase as PurchaseData;
use Greendot\EshopBundle\DataLayer\Factory\PurchaseFactory;
use Greendot\EshopBundle\DataLayer\Factory\UserDataFactory;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\DataLayer\ConfirmationPurchaseTracker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class ConfirmationPurchaseTrackerTest extends TestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
    }

    public function testPendingPurchaseIsReturnedOnlyOnce(): void
    {
        $tracker = $this->createTracker();
        $purchase = $this->createPurchase();
        $tracker->markPending($purchase);

        $event = $tracker->consume($purchase);

        self::assertSame('purchase', $event['event']);
        self::assertSame('987', $event['ecommerce']->transaction_id);
        self::assertNull($tracker->consume($purchase, true));
    }

    public function testNotPendingPurchaseIsIgnoredWhenNotJustCreated(): void
    {
        self::assertNull($this->createTracker()->consume($this->createPurchase()));
    }

    public function testFreshPurchaseFallsBackWhenSessionWasLost(): void
    {
        self::assertNotNull($this->createTracker()->consume($this->createPurchase(), true));
    }

    public function testUserDataIsSentOnlyForPendingPurchase(): void
    {
        $tracker = $this->createTracker();
        $pending = $this->createPurchase();
        $tracker->markPending($pending);

        self::assertArrayHasKey('user_data', $tracker->consume($pending));
        self::assertArrayNotHasKey('user_data', $this->createTracker()->consume($this->createPurchase(id: 988), true));
    }

    public function testOldPurchaseDoesNotFallBack(): void
    {
        $purchase = $this->createPurchase(dateIssue: new \DateTime('-3 days'));

        self::assertNull($this->createTracker()->consume($purchase, true));
    }

    public function testDraftWithoutOrderNumberIsIgnored(): void
    {
        $tracker = $this->createTracker();
        $purchase = $this->createPurchase(orderNumber: null);
        $tracker->markPending($purchase);

        self::assertNull($tracker->consume($purchase, true));
    }

    public function testDisabledTrackerNeverReturnsEvent(): void
    {
        $tracker = $this->createTracker(enabled: false);
        $purchase = $this->createPurchase();
        $tracker->markPending($purchase);

        self::assertFalse($tracker->isEnabled());
        self::assertNull($tracker->consume($purchase, true));
    }

    private function createTracker(bool $enabled = true): ConfirmationPurchaseTracker
    {
        $purchaseFactory = $this->createMock(PurchaseFactory::class);
        $purchaseFactory->method('create')->willReturnCallback(
            static fn (Purchase $purchase) => new PurchaseData((string) $purchase->getId(), 1500, 260, 99, 'CZK', 'new', []),
        );

        $userDataFactory = $this->createMock(UserDataFactory::class);
        $userDataFactory->method('create')->willReturn(['sha256_email_address' => 'hash']);

        return new ConfirmationPurchaseTracker($this->requestStack, $purchaseFactory, $userDataFactory, $enabled);
    }

    private function createPurchase(?int $orderNumber = 2026001, ?\DateTime $dateIssue = null, int $id = 987): Purchase
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getId')->willReturn($id);
        $purchase->method('getOrderNumber')->willReturn($orderNumber);
        $purchase->method('getDateIssue')->willReturn($dateIssue ?? new \DateTime('-1 hour'));

        return $purchase;
    }
}
