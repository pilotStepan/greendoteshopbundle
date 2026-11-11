<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\EventSubscriber\PurchaseIssueSubscriber;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

/**
 * Drives a real purchase_flow-shaped Workflow (multi-place marking like the projects' config) to check when
 * date_issue gets set - and above all that a checkout that fails never leaves a cart with an issue date.
 */
class PurchaseIssueSubscriberTest extends TestCase
{
    private const ORDER_PLACES = ['log_pending', 'pay_pending', 'log_track_cancellable', 'pay_track_cancellable'];

    private EventDispatcher $dispatcher;
    private Workflow $workflow;

    protected function setUp(): void
    {
        $definition = (new DefinitionBuilder(
            [PWC::S_DRAFT->value, PWC::S_WISHLIST->value, PWC::S_CART->value, ...self::ORDER_PLACES],
            [
                new Transition(PWC::T_INIT_WISHLIST->value, PWC::S_DRAFT->value, PWC::S_WISHLIST->value),
                new Transition(PWC::T_INIT_CART->value, PWC::S_DRAFT->value, PWC::S_CART->value),
                new Transition(PWC::T_CHECKOUT->value, PWC::S_CART->value, self::ORDER_PLACES),
                new Transition(PWC::T_INIT_ORDER->value, PWC::S_DRAFT->value, self::ORDER_PLACES),
            ],
        ))->build();

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addSubscriber(new PurchaseIssueSubscriber());
        $this->workflow = new Workflow($definition, new MethodMarkingStore(false, 'marking'), $this->dispatcher, PWC::NAME->value);
    }

    public function testSubscribesToCompletedOfBothTransitionsThatCreateAnOrder(): void
    {
        $this->assertSame(
            [
                PWC::eventName('completed', PWC::T_CHECKOUT) => ['issue', PurchaseIssueSubscriber::PRIORITY],
                PWC::eventName('completed', PWC::T_INIT_ORDER) => ['issue', PurchaseIssueSubscriber::PRIORITY],
            ],
            PurchaseIssueSubscriber::getSubscribedEvents(),
        );
    }

    public function testCheckoutIssuesThePurchase(): void
    {
        $purchase = $this->cart();

        $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);

        $this->assertTrue($purchase->isIssued());
        $this->assertFalse($purchase->hasPlace(PWC::S_CART->value));
    }

    public function testInitOrderIssuesThePurchase(): void
    {
        $purchase = new Purchase();

        $this->workflow->apply($purchase, PWC::T_INIT_ORDER->value);

        $this->assertTrue($purchase->isIssued());
    }

    public function testCreatingCartOrWishlistDoesNotIssue(): void
    {
        $cart = new Purchase();
        $this->workflow->apply($cart, PWC::T_INIT_CART->value);
        $wishlist = new Purchase();
        $this->workflow->apply($wishlist, PWC::T_INIT_WISHLIST->value);

        $this->assertNull($cart->getDateIssue());
        $this->assertNull($wishlist->getDateIssue());
    }

    public function testAlreadyIssuedPurchaseKeepsItsDate(): void
    {
        $issuedAt = new \DateTime('2026-01-15 10:00:00');
        $purchase = $this->cart();
        (new \ReflectionProperty(Purchase::class, 'date_issue'))->setValue($purchase, $issuedAt);

        $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);

        $this->assertEquals($issuedAt, $purchase->getDateIssue());
    }

    public function testIssuedOnlyAfterTheMarkingChangedAndBeforeOtherTransitionCompletedListeners(): void
    {
        $seen = [];
        $this->dispatcher->addListener(PWC::eventName('transition', PWC::T_CHECKOUT), function (Event $e) use (&$seen) {
            $seen['transition'] = $e->getSubject()->getDateIssue();
        });
        $this->dispatcher->addListener(PWC::eventName('completed'), function (Event $e) use (&$seen) {
            $seen['completed'] = $e->getSubject()->getDateIssue();
        });
        $this->dispatcher->addListener(PWC::eventName('completed', PWC::T_CHECKOUT), function (Event $e) use (&$seen) {
            $seen['completed.checkout'] = $e->getSubject()->getDateIssue();
        });

        $this->workflow->apply($this->cart(), PWC::T_CHECKOUT->value);

        $this->assertNull($seen['transition'], 'Must not be issued while the transition can still fail');
        $this->assertNull($seen['completed'], 'The generic completed is dispatched before completed.checkout');
        $this->assertNotNull($seen['completed.checkout']);
    }

    public function testBlockedCheckoutLeavesCartUnissued(): void
    {
        $this->dispatcher->addListener(PWC::eventName('guard', PWC::T_CHECKOUT), function (GuardEvent $e) {
            $e->setBlocked(true, 'Nelze vytvořit prázdnou objednávku');
        });
        $purchase = $this->cart();

        try {
            $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);
            $this->fail('Blocked transition must throw');
        } catch (NotEnabledTransitionException) {
        }

        $this->assertNull($purchase->getDateIssue());
        $this->assertTrue($purchase->hasPlace(PWC::S_CART->value));
    }

    public function testCheckoutFailingInAListenerLeavesCartUnissued(): void
    {
        $this->dispatcher->addListener(PWC::eventName('transition', PWC::T_CHECKOUT), function () {
            throw new \RuntimeException('voucher service down');
        });
        $purchase = $this->cart();

        try {
            $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);
            $this->fail('Listener exception must propagate');
        } catch (\RuntimeException) {
        }

        $this->assertNull($purchase->getDateIssue());
        $this->assertTrue($purchase->hasPlace(PWC::S_CART->value));
    }

    public function testCheckoutFailingAfterEnteringLeavesCartUnissued(): void
    {
        $this->dispatcher->addListener('workflow.' . PWC::NAME->value . '.entered', function () {
            throw new \RuntimeException('entered listener failed');
        });
        $purchase = $this->cart();

        try {
            $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);
            $this->fail('Listener exception must propagate');
        } catch (\RuntimeException) {
        }

        $this->assertNull($purchase->getDateIssue());
    }

    public function testFailedCheckoutCanBeRetried(): void
    {
        $listener = new class {
            public bool $fail = true;

            public function __invoke(): void
            {
                if ($this->fail) {
                    throw new \RuntimeException('temporary');
                }
            }
        };
        $this->dispatcher->addListener(PWC::eventName('transition', PWC::T_CHECKOUT), $listener);
        $purchase = $this->cart();

        try {
            $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);
            $this->fail('The first checkout must fail.');
        } catch (\Throwable $e) {
            $this->assertSame('temporary', $e->getMessage());
        }
        $listener->fail = false;
        $this->workflow->apply($purchase, PWC::T_CHECKOUT->value);

        $this->assertTrue($purchase->isIssued());
    }

    private function cart(): Purchase
    {
        $purchase = new Purchase();
        $purchase->setMarking([PWC::S_CART->value => 1]);

        return $purchase;
    }
}
