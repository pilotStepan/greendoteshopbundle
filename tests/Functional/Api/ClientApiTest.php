<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ClientFactory;
use Symfony\Component\HttpFoundation\Response;

class ClientApiTest extends ApiTestCase
{
    public function testRegistrationCreatesClientWithoutLeakingPassword(): void
    {
        $this->client->request('POST', '/clients', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Jane',
            'surname' => 'Doe',
            'mail' => 'jane.doe@example.com',
            'password' => 'super-secret',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    public function testSelfCanReadOwnRecord(): void
    {
        $client = ClientFactory::createOne();
        $this->client->loginUser($client->object());

        $this->client->request('GET', '/clients/' . $client->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testCannotReadAnotherClientsRecord(): void
    {
        $owner = ClientFactory::createOne();
        $other = ClientFactory::createOne();
        $this->client->loginUser($other->object());

        $this->client->request('GET', '/clients/' . $owner->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnonymousCannotReadAnyClient(): void
    {
        $client = ClientFactory::createOne();

        $this->client->request('GET', '/clients/' . $client->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
