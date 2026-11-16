<?php

namespace Greendot\EshopBundle\Tests\Functional\Doctrine;

use Greendot\EshopBundle\Doctrine\DoctrineFiltersConfigNames;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\EventSubscriber\IssuedPurchaseReferencesListener;
use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ClientFactory;
use Greendot\EshopBundle\Tests\App\Factory\CurrencyFactory;
use Greendot\EshopBundle\Tests\App\Factory\PaymentTypeFactory;
use Greendot\EshopBundle\Tests\App\Factory\ProductFactory;
use Greendot\EshopBundle\Tests\App\Factory\ProductVariantFactory;
use Greendot\EshopBundle\Tests\App\Factory\PurchaseFactory;
use Greendot\EshopBundle\Tests\App\Factory\TransportationFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hiding something from the catalog must not break the purchases that already reference it.
 *
 * Regression: GET /simple/api/purchases/{id}/invoice/pdf threw EntityNotFoundException
 * ("Entity of type Transportation for IDs id(4) was not found") after the order's transportation was disabled,
 * because TransportationIsEnabledFilter also applies to the lazy load of Purchase::$Transportation.
 *
 * @see IssuedPurchaseReferencesListener
 */
class IssuedPurchaseReferencesTest extends ApiTestCase
{
    public function testIssuedPurchaseLoadsItsDisabledTransportation(): void
    {
        $transportation = TransportationFactory::createOne(['name' => 'PPL']);
        $purchaseId = $this->createIssuedPurchase(['transportation' => $transportation]);
        $transportation->object()->setIsEnabled(false);

        $purchase = $this->reload($purchaseId);

        self::assertSame('PPL', $purchase->getTransportation()->getName());
    }

    public function testIssuedPurchaseLoadsItsInactiveVariantAndProduct(): void
    {
        $product = ProductFactory::createOne(['name' => 'Air conditioner']);
        $variant = ProductVariantFactory::createOne(['name' => '3.5 kW', 'product' => $product]);
        $purchaseId = $this->createIssuedPurchase();
        $this->addLine($purchaseId, $variant->getId());
        $variant->object()->setIsActive(false);
        $product->object()->setIsActive(false);

        $line = $this->reload($purchaseId)->getProductVariants()->first();

        self::assertInstanceOf(PurchaseProductVariant::class, $line);
        self::assertSame('3.5 kW', $line->getProductVariant()->getName());
        self::assertSame('Air conditioner', $line->getProductVariant()->getProduct()->getName());
    }

    public function testIssuedPurchaseLoadsItsSoftDeletedCurrency(): void
    {
        $currency = CurrencyFactory::createOne(['name' => 'Koruna', 'symbol' => 'Kč', 'isDefault' => false]);
        $purchaseId = $this->createIssuedPurchase(['currency' => $currency]);
        $currency->object()->setIsDeleted(true);

        $purchase = $this->reload($purchaseId);

        self::assertSame('Kč', $purchase->getCurrency()->getSymbol());
    }

    public function testIssuedPurchaseLoadsAPaymentTypeHiddenByATenantFilter(): void
    {
        $paymentType = PaymentTypeFactory::createOne(['name' => 'Cash on delivery']);
        $purchaseId = $this->createIssuedPurchase(['paymentType' => $paymentType]);
        $paymentType->object()->setIsEnabled(false);

        $purchase = $this->reload($purchaseId);

        self::assertSame('Cash on delivery', $purchase->getPaymentType()->getName());
    }

    public function testCatalogStaysFilteredAfterAPurchaseLoadsHiddenReferences(): void
    {
        $transportation = TransportationFactory::createOne();
        $purchaseId = $this->createIssuedPurchase(['transportation' => $transportation]);
        $transportation->object()->setIsEnabled(false);

        $this->reload($purchaseId)->getTransportation()->getName();

        $em = $this->getEntityManager();
        self::assertTrue($em->getFilters()->isEnabled(DoctrineFiltersConfigNames::TransportationIsEnabledFilter->value));
        self::assertSame([], $em->getRepository(Transportation::class)->findAll());
    }

    public function testCartDoesNotLoadItsDisabledTransportation(): void
    {
        $transportation = TransportationFactory::createOne();
        $purchaseId = PurchaseFactory::createOne(['transportation' => $transportation])->getId();
        $transportation->object()->setIsEnabled(false);

        $cart = $this->reload($purchaseId);

        self::assertTrue($this->getEntityManager()->getUnitOfWork()->isUninitializedObject($cart->getTransportation()));
    }

    public function testOwnerReadsIssuedPurchaseWithDisabledTransportationThroughApi(): void
    {
        $owner = ClientFactory::createOne();
        $transportation = TransportationFactory::createOne(['name' => 'PPL']);
        $purchaseId = $this->createIssuedPurchase(['client' => $owner, 'transportation' => $transportation]);
        $transportation->object()->setIsEnabled(false);
        $this->reload($purchaseId);
        $this->client->loginUser($owner->object());

        $this->client->request('GET', '/purchases/' . $purchaseId, [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('PPL', $data['Transportation']['name'] ?? null);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createIssuedPurchase(array $attributes = []): int
    {
        $purchase = PurchaseFactory::createOne($attributes);
        $purchase->object()->issue();
        $this->getEntityManager()->flush();

        return $purchase->getId();
    }

    /**
     * Plain SQL: adding a line through the ORM fires the storefront cart listeners, which need a session.
     */
    private function addLine(int $purchaseId, int $variantId): void
    {
        $this->getEntityManager()->getConnection()->insert('purchase_product_variant', [
            'purchase_id' => $purchaseId,
            'product_variant_id' => $variantId,
            'amount' => 1,
        ]);
    }

    /**
     * Flushes what the test changed and loads the purchase fresh, so its references are lazy again.
     */
    private function reload(int $purchaseId): Purchase
    {
        $em = $this->getEntityManager();
        $em->flush();
        $em->clear();

        $purchase = $em->find(Purchase::class, $purchaseId);
        self::assertNotNull($purchase);

        return $purchase;
    }
}
