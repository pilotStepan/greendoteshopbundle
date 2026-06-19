<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ProductFactory;
use Greendot\EshopBundle\Tests\App\Factory\ProductVariantFactory;
use Symfony\Component\HttpFoundation\Response;

class ProductApiTest extends ApiTestCase
{
    public function testCollectionIsPubliclyReadable(): void
    {
        ProductFactory::createMany(2);

        $this->client->request('GET', '/products', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testItemEnrichesCalculatedPrices(): void
    {
        $product = ProductFactory::createOne();
        ProductVariantFactory::createOne(['product' => $product]);

        $this->client->request('GET', '/products/' . $product->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('calculatedPrices', $data);
    }

    public function testCreateIsForbiddenForAnonymous(): void
    {
        $this->client->request('POST', '/products', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'New product', 'slug' => 'new-product', 'isActive' => true, 'isVisible' => true]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
