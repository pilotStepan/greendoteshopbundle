<?php

namespace Greendot\EshopBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Groups;
use Greendot\EshopBundle\Entity\Project\Purchase;

/**
 * date_created is fixed at creation; date_issue is null until the purchase is issued, write-once and never writable
 * through the API. Prices resolve to date_issue, or now while not issued.
 */
class PurchaseDatesTest extends TestCase
{
    public function testNewPurchaseIsCreatedButNotIssued(): void
    {
        $before = new \DateTimeImmutable();
        $purchase = new Purchase();

        $this->assertGreaterThanOrEqual($before, $purchase->getDateCreated());
        $this->assertNull($purchase->getDateIssue());
        $this->assertFalse($purchase->isIssued());
    }

    public function testDateIssueHasNoSetter(): void
    {
        $this->assertFalse(method_exists(Purchase::class, 'setDateIssue'));
        $this->assertFalse(method_exists(Purchase::class, 'setDateCreated'));
    }

    public function testIssueSetsTheDateOnce(): void
    {
        $purchase = new Purchase();
        $before = new \DateTime();

        $purchase->issue();

        $this->assertTrue($purchase->isIssued());
        $this->assertGreaterThanOrEqual($before, $purchase->getDateIssue());
    }

    public function testIssueNeverOverwritesAnExistingDate(): void
    {
        $issuedAt = new \DateTime('2026-01-15 10:00:00');
        $purchase = $this->issuedAt($issuedAt);

        $purchase->issue();

        $this->assertEquals($issuedAt, $purchase->getDateIssue());
    }

    public function testPricingDateIsNowWhileNotIssued(): void
    {
        $purchase = new Purchase();
        (new \ReflectionProperty(Purchase::class, 'date_created'))->setValue($purchase, new \DateTimeImmutable('-30 days'));
        $before = new \DateTime();

        $pricingDate = $purchase->getPricingDate();

        $this->assertGreaterThanOrEqual($before, $pricingDate);
        $this->assertNull($purchase->getDateIssue(), 'Resolving a price must not issue the purchase');
    }

    public function testPricingDateIsTheIssueDateOnceIssued(): void
    {
        $issuedAt = new \DateTime('2026-01-15 10:00:00');
        $purchase = $this->issuedAt($issuedAt);

        $this->assertEquals($issuedAt, $purchase->getPricingDate());
    }

    public function testModifyingThePricingDateDoesNotTouchTheIssueDate(): void
    {
        $purchase = $this->issuedAt(new \DateTime('2026-01-15 10:00:00'));

        $purchase->getPricingDate()->modify('+1 year');

        $this->assertEquals(new \DateTime('2026-01-15 10:00:00'), $purchase->getDateIssue());
    }

    public function testDatesAreReadOnlyInTheApi(): void
    {
        $this->assertSame(['purchase:read'], $this->groupsOf('date_issue'));
        $this->assertSame(['purchase:read', 'purchase:wishlist'], $this->groupsOf('date_created'));
    }

    private function issuedAt(\DateTime $date): Purchase
    {
        $purchase = new Purchase();
        (new \ReflectionProperty(Purchase::class, 'date_issue'))->setValue($purchase, $date);

        return $purchase;
    }

    /** @return string[] */
    private function groupsOf(string $property): array
    {
        $attributes = (new \ReflectionProperty(Purchase::class, $property))->getAttributes(Groups::class);
        $this->assertCount(1, $attributes);

        return $attributes[0]->newInstance()->groups;
    }
}
