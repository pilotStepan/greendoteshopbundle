<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\CurrencyFactory;
use Greendot\EshopBundle\Tests\App\Factory\PaymentTypeFactory;
use Greendot\EshopBundle\Tests\App\Factory\PurchaseFactory;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the currency snapshot + Money fields added to the cart/purchase session endpoints:
 * a purchase carries its own currency, and switching to a PaymentType with a different
 * currency flips both `currency` and every `*Money` field in the same PATCH response.
 */
class PurchaseSessionMoneyTest extends ApiTestCase
{
    public function testSessionPurchaseExposesCurrencyAndMoneyFields(): void
    {
        $currency = CurrencyFactory::createOne(['name' => 'CZK', 'symbol' => 'Kč', 'rounding' => 0]);
        $purchase = PurchaseFactory::createOne(['currency' => $currency]);
        $this->putPurchaseInSession($purchase->getId());

        $this->client->request('GET', '/purchases/session', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('/currencies/' . $currency->getId(), $data['currency']);
        $this->assertSame('CZK', $data['totalMoney']['iso']);
        $this->assertSame('CZK', $data['totalMoneyNoServices']['iso']);
        $this->assertArrayHasKey('priceVat', $data['calculatedMoney']);
        $this->assertSame('CZK', $data['calculatedMoney']['priceVat']['iso']);
    }

    public function testPatchingPaymentTypeWithDifferentCurrencyFlipsCurrencyAndMoney(): void
    {
        $czk = CurrencyFactory::createOne(['name' => 'CZK', 'symbol' => 'Kč', 'rounding' => 0]);
        $eur = CurrencyFactory::createOne(['name' => 'EUR', 'symbol' => '€', 'rounding' => 2, 'isDefault' => false]);
        $paymentType = PaymentTypeFactory::createOne(['currency' => $eur]);
        $purchase = PurchaseFactory::createOne(['currency' => $czk]);
        $this->putPurchaseInSession($purchase->getId());

        $this->client->request(
            'PATCH',
            '/purchases/session',
            [], [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/merge-patch+json'],
            json_encode(['PaymentType' => '/payment_types/' . $paymentType->getId()]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('/currencies/' . $eur->getId(), $data['currency']);
        $this->assertSame('EUR', $data['totalMoney']['iso']);
        $this->assertSame('EUR', $data['calculatedMoney']['priceVat']['iso']);
    }

    private function putPurchaseInSession(int $purchaseId): void
    {
        $session = self::getContainer()->get('session.factory')->createSession();
        $session->set('purchase', $purchaseId);
        $session->save();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }
}
