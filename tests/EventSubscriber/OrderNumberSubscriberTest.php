<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\EventSubscriber\OrderNumberSubscriber;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

class OrderNumberSubscriberTest extends TestCase
{
    public function testSubscribesToBothTransitionsThatCreateAnOrder(): void
    {
        $events = OrderNumberSubscriber::getSubscribedEvents();

        $this->assertSame('assignOrderNumber', $events[PWC::eventName('transition', PWC::T_CHECKOUT)]);
        $this->assertSame('assignOrderNumber', $events[PWC::eventName('transition', PWC::T_INIT_ORDER)]);
        $this->assertCount(2, $events);
    }

    public function testAssignsOrderNumberThroughRepository(): void
    {
        $purchase = new Purchase();

        $repository = $this->createMock(PurchaseRepository::class);
        $repository->expects($this->once())
            ->method('assignNextOrderNumber')
            ->with($purchase)
            ->willReturnCallback(fn(Purchase $p) => $p->assignOrderNumber(1001))
        ;

        (new OrderNumberSubscriber($repository))->assignOrderNumber(
            new TransitionEvent($purchase, new Marking(), new Transition(PWC::T_CHECKOUT->value, 'cart', 'log_pending')),
        );

        $this->assertSame(1001, $purchase->getOrderNumber());
    }

    public function testNewPurchaseHasNoOrderNumber(): void
    {
        $this->assertNull((new Purchase())->getOrderNumber());
    }

    public function testOrderNumberCannotBeReassigned(): void
    {
        $purchase = (new Purchase())->assignOrderNumber(1001);

        $this->expectException(LogicException::class);
        $purchase->assignOrderNumber(1002);
    }
}
