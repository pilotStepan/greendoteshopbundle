<?php

namespace Greendot\EshopBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountType;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;


class ManageClientDiscountTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ManageClientDiscount $manageClientDiscount;
    private Client $client;
    private Client $otherClient;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('wrapInTransaction')
            ->willReturnCallback(function (callable $func) { return $func(); });

        $this->manageClientDiscount = new ManageClientDiscount($this->entityManager);
        $this->client = new Client();
        $this->otherClient = new Client();
    }

    public function testGuardUseThrowsWhenDiscountIsUsed(): void
    {
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->guardUse($discount, new Purchase());
    }

    public function testGuardUseDoesNotThrowWithActiveDates(): void
    {
        $discount = new ClientDiscount();
        $discount->setDateStart(new \DateTime('-1 day'))
            ->setDateEnd(new \DateTime('+1 day'));

        $this->manageClientDiscount->guardUse($discount, new Purchase());
        $this->addToAssertionCount(1);
    }

    public function testGuardUseThrowsWithPastEndDate(): void
    {
        $discount = new ClientDiscount();
        $discount->setDateStart(new \DateTime('-2 days'))
            ->setDateEnd(new \DateTime('-1 day'));

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->guardUse($discount, new Purchase());
    }

    public function testGuardUseThrowsWithFutureStartDate(): void
    {
        $discount = new ClientDiscount();
        $discount->setDateStart(new \DateTime('+1 day'))
            ->setDateEnd(new \DateTime('+2 days'));

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->guardUse($discount, new Purchase());
    }

    public function testGuardUseThrowsForInvalidDiscount(): void
    {
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->guardUse($discount, new Purchase());
    }

    public function testGuardUseDoesNotThrowForClientSpecificValid(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleClient)
            ->setClient($this->client);

        $purchase = (new Purchase())->setClient($this->client);

        $this->manageClientDiscount->guardUse($discount, $purchase);
        $this->addToAssertionCount(1);
    }

    public function testGuardUseThrowsForWrongClient(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUseClient)
            ->setClient($this->client);

        $purchase = (new Purchase())->setClient($this->otherClient);

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->guardUse($discount, $purchase);
    }

    public function testGuardUseDoesNotThrowForNonClientSpecific(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::MultiUse);

        $this->manageClientDiscount->guardUse($discount, new Purchase());
        $this->addToAssertionCount(1);
    }

    public function testUseThrowsWhenInvalid(): void
    {
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->use($discount, new Purchase());
    }

    public function testUseMarksSingleUseAsUsed(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUse);
        $purchase = new Purchase();

        $this->manageClientDiscount->use($discount, $purchase);

        $this->assertTrue($discount->isIsUsed());
    }

    public function testUseMarksSingleUseClientAsUsed(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUseClient)
            ->setClient($this->client);
        $purchase = (new Purchase())->setClient($this->client);

        $this->manageClientDiscount->use($discount, $purchase);

        $this->assertTrue($discount->isIsUsed());
    }

    public function testUseDoesNotMarkMultiUseAsUsed(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::MultiUse);
        $discount->setIsUsed(false);
        $purchase = new Purchase();

        $this->manageClientDiscount->use($discount, $purchase);

        $this->assertFalse($discount->isIsUsed());
    }

    public function testUseThrowsWithWrongClient(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::SingleUseClient)
            ->setClient($this->client);
        $purchase = (new Purchase())->setClient($this->otherClient);

        $this->expectException(LogicException::class);
        $this->manageClientDiscount->use($discount, $purchase);
    }

    public function testUseSetsClientDiscountOnPurchase(): void
    {
        $discount = new ClientDiscount();
        $discount->setType(DiscountType::MultiUse);
        $purchase = new Purchase();

        $this->manageClientDiscount->use($discount, $purchase);

        $this->assertSame($discount, $purchase->getClientDiscount());
    }
}
