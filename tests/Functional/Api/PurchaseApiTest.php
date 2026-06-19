<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ClientFactory;
use Greendot\EshopBundle\Tests\App\Factory\PurchaseFactory;
use Symfony\Component\HttpFoundation\Response;

class PurchaseApiTest extends ApiTestCase
{
    public function testCollectionRequiresAdmin(): void
    {
        $this->client->request('GET', '/purchases', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testOwnerCanReadOwnPurchase(): void
    {
        $owner = ClientFactory::createOne();
        $purchase = PurchaseFactory::createOne(['client' => $owner]);
        $this->client->loginUser($owner->object());

        $this->client->request('GET', '/purchases/' . $purchase->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testNonOwnerCannotReadAnotherClientsPurchase(): void
    {
        $owner = ClientFactory::createOne();
        $other = ClientFactory::createOne();
        $purchase = PurchaseFactory::createOne(['client' => $owner]);
        $this->client->loginUser($other->object());

        $this->client->request('GET', '/purchases/' . $purchase->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSessionEndpointReturnsNoContentWithoutACart(): void
    {
        $this->client->request('GET', '/purchases/session', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
