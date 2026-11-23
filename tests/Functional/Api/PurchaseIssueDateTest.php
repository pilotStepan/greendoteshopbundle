<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\EventSubscriber\OrderNumberSubscriber;
use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\PurchaseFactory;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * date_issue is write-once: never writable through the API, set only when checkout completes,
 * and a checkout that fails inside its DB transaction (like PurchaseCheckoutProcessor runs it) leaves the cart unissued.
 */
class PurchaseIssueDateTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Order numbers are not under test here (PurchaseOrderNumberTest covers them).
        $this->dispatcher()->removeSubscriber(self::getContainer()->get(OrderNumberSubscriber::class));
    }

    public function testCustomerCannotWriteDateIssueToSessionCart(): void
    {
        $cart = $this->createCart();
        $this->putPurchaseInSession($cart->getId());

        $this->client->request('PATCH', '/purchases/session', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], json_encode(['date_issue' => '2020-01-01T00:00:00+00:00', 'date_created' => '2020-01-01T00:00:00+00:00']));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($data['date_issue'] ?? null, 'Null fields are skipped in the response; it must not be the sent date');
        $this->assertStringNotContainsString('2020-01-01', $data['date_created']);
        $this->assertSame([null, true], $this->storedDateIssueAndIsCart($cart->getId()));
    }

    public function testCompletedCheckoutIssuesThePurchase(): void
    {
        $cart = $this->createCart();
        $em = $this->getEntityManager();

        $em->wrapInTransaction(fn() => $this->workflow()->apply($em->find(Purchase::class, $cart->getId()), PWC::T_CHECKOUT->value));

        [$dateIssue, $isCart] = $this->storedDateIssueAndIsCart($cart->getId());
        $this->assertNotNull($dateIssue);
        $this->assertFalse($isCart);
    }

    public function testCheckoutFailingInsideItsTransactionLeavesTheCartUnissued(): void
    {
        $cart = $this->createCart();
        $this->dispatcher()->addListener(PWC::eventName('transition', PWC::T_CHECKOUT), function () {
            throw new \RuntimeException('checkout listener failed');
        });
        $em = $this->getEntityManager();

        try {
            $em->wrapInTransaction(fn() => $this->workflow()->apply($em->find(Purchase::class, $cart->getId()), PWC::T_CHECKOUT->value));
            $this->fail('Listener exception must propagate');
        } catch (\RuntimeException) {
        }

        $this->assertFalse($em->isOpen(), 'A failed checkout transaction closes the EntityManager, nothing else can be flushed');
        $this->assertSame([null, true], $this->storedDateIssueAndIsCart($cart->getId()));
    }

    private function createCart(): Purchase
    {
        $cart = PurchaseFactory::createOne()->object();
        $cart->setMarking([PWC::S_CART->value => 1]);
        $this->getEntityManager()->flush();

        return $cart;
    }

    /** @return array{0: ?string, 1: bool} date_issue and whether the marking is still `cart`, read straight from the DB */
    private function storedDateIssueAndIsCart(int $id): array
    {
        $row = $this->getEntityManager()->getConnection()
            ->fetchAssociative('SELECT date_issue, marking FROM purchase WHERE id = ?', [$id]);

        return [$row['date_issue'], array_key_exists(PWC::S_CART->value, json_decode($row['marking'], true))];
    }

    /**
     * Multi-place purchase_flow as the projects configure it, on the container's real event dispatcher (so the
     * autoconfigured PurchaseIssueSubscriber service is what runs). The test kernel's own purchase_flow is a
     * single-state state_machine that does not fit Purchase::$marking.
     */
    private function workflow(): WorkflowInterface
    {
        $orderPlaces = ['log_pending', 'pay_pending'];
        $definition = (new DefinitionBuilder(
            [PWC::S_CART->value, ...$orderPlaces],
            [new Transition(PWC::T_CHECKOUT->value, PWC::S_CART->value, $orderPlaces)],
        ))->build();

        return new Workflow($definition, new MethodMarkingStore(false, 'marking'), $this->dispatcher(), PWC::NAME->value);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return self::getContainer()->get('event_dispatcher');
    }

    private function putPurchaseInSession(int $purchaseId): void
    {
        $session = self::getContainer()->get('session.factory')->createSession();
        $session->set('purchase', $purchaseId);
        $session->save();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }
}
