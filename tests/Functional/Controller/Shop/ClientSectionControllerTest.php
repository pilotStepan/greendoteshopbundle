<?php

namespace Greendot\EshopBundle\Tests\Functional\Controller\Shop;

use Greendot\EshopBundle\Tests\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Smoke tests for ClientSectionController.
 *
 * We test the /api/ares-{ico} endpoint because it:
 * - requires no authentication
 * - returns JSON (no Twig needed)
 * - has input validation that fires before any external HTTP call
 *
 * These tests prove the route is reachable and the controller responds
 * with the expected JSON shape for invalid input — without hitting ARES.
 */
class ClientSectionControllerTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    public function testAresRejectsTooShortIco(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/ares-123');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('statusText', $data);
        $this->assertSame('Špatný formát IČO', $data['statusText']);
    }

    public function testAresRejectsNonNumericIco(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/ares-abcdefgh');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('statusText', $data);
        $this->assertSame('Špatný formát IČO', $data['statusText']);
    }

    public function testAresRejectsTooLongIco(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/ares-123456789');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('statusText', $data);
        $this->assertSame('Špatný formát IČO', $data['statusText']);
    }
}
