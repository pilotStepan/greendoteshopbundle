<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ParameterFactory;
use Symfony\Component\HttpFoundation\Response;

class ParameterApiTest extends ApiTestCase
{
    public function testCollectionIsPubliclyReadable(): void
    {
        ParameterFactory::createMany(2);

        $this->client->request('GET', '/parameters', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testFilteredParametersReturnsResults(): void
    {
        ParameterFactory::createOne(['data' => 'red']);

        $this->client->request('GET', '/parametersFiltered', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testCreateRequiresAdmin(): void
    {
        $this->client->request('POST', '/parameters', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['data' => 'blue']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
