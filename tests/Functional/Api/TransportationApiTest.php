<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\TransportationFactory;
use Symfony\Component\HttpFoundation\Response;

class TransportationApiTest extends ApiTestCase
{
    public function testCollectionIsPubliclyReadable(): void
    {
        TransportationFactory::createMany(2);

        $this->client->request('GET', '/transportations', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testDisabledTransportationIsHiddenByDefault(): void
    {
        $transportation = TransportationFactory::createOne(['isEnabled' => false]);

        $this->client->request('GET', '/transportations/' . $transportation->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMutationRequiresAdmin(): void
    {
        $this->client->request('POST', '/transportations', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Courier',
            'description' => 'desc',
            'descriptionMail' => 'desc mail',
            'descriptionDuration' => 2,
            'html' => '<p>x</p>',
            'icon' => 'icon.svg',
            'duration' => 2,
            'squence' => 1,
            'country' => 'CZ',
            'stateUrl' => 'https://example.test',
            'transportationAction' => 'delivery',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
