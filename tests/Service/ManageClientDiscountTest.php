<?php

namespace Greendot\EshopBundle\Tests\Service;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use PHPUnit\Framework\TestCase;


class ManageClientDiscountTest extends TestCase
{
    private ManageClientDiscount $manageClientDiscount;
    private Client $client;
    private Client $otherClient;

    protected function setUp(): void
    {
        $this->manageClientDiscount = new ManageClientDiscount();
        $this->client = new Client();
        $this->otherClient = new Client();
    }

    public function testIsValidWhenUsed(): void
    {
        // Discount flagged as used should be invalid.
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);

        $this->assertFalse($this->manageClientDiscount->isValid($discount));
    }

    public function testIsValidWithActiveDates(): void
    {
        // Discount with dates covering the current time should be valid.
        $discount = new ClientDiscount();
        $discount->setDateStart(new \DateTime('-1 day'))
            ->setDateEnd(new \DateTime('+1 day'));

        $this->assertTrue($this->manageClientDiscount->isValid($discount));
    }

    public function testIsValidWithPastEndDate(): void
    {
        // Discount whose end date is past should be invalid.
        $discount = new ClientDiscount();
        $discount->setDateStart(new \DateTime('-2 days'))
            ->setDateEnd(new \DateTime('-1 day'));

        $this->assertFalse($this->manageClientDiscount->isValid($discount));
    }

    public function testIsValidWithFutureStartDate(): void
    {
        // Discount that hasn't started yet should be invalid.
        $discount = new ClientDiscount();
        $discount->setDateStart(new \DateTime('+1 day'))
            ->setDateEnd(new \DateTime('+2 days'));

        $this->assertFalse($this->manageClientDiscount->isValid($discount));
    }

    public function testIsAvailableWithoutDiscount(): void
    {
        // No discount provided should return false.
        $purchase = new Purchase();
        $this->assertFalse($this->manageClientDiscount->isAvailable($purchase, null));
    }

    public function testIsAvailableWithInvalidDiscount(): void
    {
        // A discount already used is invalid and not available.
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);
        $purchase = new Purchase();

        $this->assertFalse($this->manageClientDiscount->isAvailable($purchase, $discount));
    }

    public function testIsAvailableWithClientSpecificValid(): void
    {
        // A client-specific discount is available when the purchase client matches.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleClient)
            ->setClient($this->client);

        $purchase = (new Purchase())->setClient($this->client);

        $this->assertTrue($this->manageClientDiscount->isAvailable($purchase, $discount));
    }

    public function testIsAvailableWithClientSpecificInvalid(): void
    {
        // A client-specific discount is unavailable when clients don't match.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUseClient)
            ->setClient($this->client);

        $purchase = (new Purchase())->setClient($this->otherClient);

        $this->assertFalse($this->manageClientDiscount->isAvailable($purchase, $discount));
    }

    public function testIsAvailableWithNonClientSpecific(): void
    {
        // A non-client-specific (multi-use) discount is available regardless of client.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::MultiUse);
        $purchase = new Purchase();

        $this->assertTrue($this->manageClientDiscount->isAvailable($purchase, $discount));
    }

    public function testUseFailsWhenInvalid(): void
    {
        // Using an invalid discount should fail.
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);
        $purchase = new Purchase();

        $this->assertFalse($this->manageClientDiscount->use($purchase, $discount));
    }

    public function testUseMarksSingleUseAsUsed(): void
    {
        // Applying a single-use discount marks it as used.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUse);
        $purchase = new Purchase();

        $this->assertTrue($this->manageClientDiscount->use($purchase, $discount));
        $this->assertTrue($discount->isIsUsed());
    }

    public function testUseMarksSingleUseClientAsUsed(): void
    {
        // Applying a single-use client discount marks it as used when client matches.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUseClient)
            ->setClient($this->client);
        $purchase = (new Purchase())->setClient($this->client);

        $this->assertTrue($this->manageClientDiscount->use($purchase, $discount));
        $this->assertTrue($discount->isIsUsed());
    }

    public function testUseDoesNotMarkMultiUseAsUsed(): void
    {
        // Using a multi-use discount should not mark it as used.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::MultiUse);
        $discount->setIsUsed(false);
        $purchase = new Purchase();

        $this->assertTrue($this->manageClientDiscount->use($purchase, $discount));
        $this->assertFalse($discount->isIsUsed());
    }

    public function testUseFailsWithWrongClient(): void
    {
        // Using a client-specific discount fails if the purchase client doesn't match.
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUseClient)
            ->setClient($this->client);
        $purchase = (new Purchase())->setClient($this->otherClient);

        $this->assertFalse($this->manageClientDiscount->use($purchase, $discount));
    }
}