<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\PurchaseFactory;

/**
 * Runs PurchaseRepository::assignNextOrderNumber()'s real `SELECT ... FOR UPDATE` against MariaDB.
 * The other functional tests switch the order-number subscriber off; order numbers are not what they test.
 */
class PurchaseOrderNumberTest extends ApiTestCase
{
    public function testFirstOrderNumberContinuesAfterTheHighestPurchaseId(): void
    {
        $purchases = [$this->createPurchase(), $this->createPurchase(), $this->createPurchase()];
        $maxId = max(array_map(fn(Purchase $p) => $p->getId(), $purchases));

        $this->repository()->assignNextOrderNumber($purchases[0]);

        $this->assertSame($maxId + 1, $purchases[0]->getOrderNumber());
    }

    public function testOrderNumbersIncrementFromTheHighestAssignedOne(): void
    {
        $first = $this->createPurchase();
        $second = $this->createPurchase();

        $this->repository()->assignNextOrderNumber($first);
        $this->getEntityManager()->flush();
        $this->repository()->assignNextOrderNumber($second);

        $this->assertSame($first->getOrderNumber() + 1, $second->getOrderNumber());
    }

    public function testAlreadyNumberedPurchaseKeepsItsNumber(): void
    {
        $purchase = $this->createPurchase();
        $this->repository()->assignNextOrderNumber($purchase);
        $this->getEntityManager()->flush();
        $number = $purchase->getOrderNumber();

        $this->createPurchase();
        $this->repository()->assignNextOrderNumber($purchase);

        $this->assertSame($number, $purchase->getOrderNumber());
    }

    private function createPurchase(): Purchase
    {
        return PurchaseFactory::createOne()->object();
    }

    private function repository(): PurchaseRepository
    {
        return $this->getEntityManager()->getRepository(Purchase::class);
    }
}
