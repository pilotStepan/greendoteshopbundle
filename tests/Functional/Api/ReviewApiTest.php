<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ProductFactory;
use Greendot\EshopBundle\Tests\App\Factory\ReviewFactory;
use Symfony\Component\HttpFoundation\Response;

class ReviewApiTest extends ApiTestCase
{
    public function testAnonymousCanCreateReview(): void
    {
        $product = ProductFactory::createOne();

        $this->client->request('POST', '/reviews', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'contents' => 'Great product',
            'stars' => 5,
            'positive' => true,
            'Product' => '/products/' . $product->getId(),
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testReviewsStatsReturnsAggregateShape(): void
    {
        $product = ProductFactory::createOne();
        ReviewFactory::createMany(3, ['product' => $product]);

        $this->client->request('GET', '/reviews-stats', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testMutationRequiresAdmin(): void
    {
        $review = ReviewFactory::createOne();

        $this->client->request('PATCH', '/reviews/' . $review->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/merge-patch+json',
        ], json_encode(['stars' => 1]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
