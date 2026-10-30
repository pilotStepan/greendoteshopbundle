<?php

namespace Greendot\EshopBundle\Tests\Service\PaymentGateway;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayClient;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayException;
use Greendot\EshopBundle\Service\PaymentGateway\GoPay\GoPayState;

class GoPayClientTest extends TestCase
{
    private const API = 'https://gw.sandbox.gopay.com/api';

    public function testTokenIsRequestedOnceAndReusedForFollowingCalls(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$method, $url, $options];

            if (str_ends_with($url, '/oauth2/token')) {
                return new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800]));
            }

            return new MockResponse(json_encode(['id' => 3000000001, 'state' => 'PAID', 'order_number' => '14048']));
        });

        $client = new GoPayClient($httpClient, 'cid', 'secret', '8925782195', self::API);

        $client->getStatus('3000000001');
        $client->getStatus('3000000001');

        $this->assertCount(3, $requests, 'one token request + two status requests');

        [$method, $url, $options] = $requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame(self::API . '/oauth2/token', $url);
        $this->assertContains('Authorization: Basic ' . base64_encode('cid:secret'), $options['headers']);
        $this->assertStringContainsString('grant_type=client_credentials', $options['body']);
        $this->assertStringContainsString('scope=payment-all', $options['body']);

        [$method, $url, $options] = $requests[1];
        $this->assertSame('GET', $method);
        $this->assertSame(self::API . '/payments/payment/3000000001', $url);
        $this->assertContains('Authorization: Bearer tok', $options['headers']);
    }

    public function testGetStatusMapsResponse(): void
    {
        $client = new GoPayClient(new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800])),
            new MockResponse(json_encode([
                'id' => 3000000001,
                'state' => 'CANCELED',
                'order_number' => '14048',
                'amount' => 15000,
                'currency' => 'CZK',
            ])),
        ]), 'cid', 'secret', '8925782195', self::API);

        $status = $client->getStatus('3000000001');

        $this->assertSame('3000000001', $status->id);
        $this->assertSame(GoPayState::CANCELED, $status->state);
        $this->assertTrue($status->state->isFailed());
        $this->assertSame('14048', $status->orderNumber);
        $this->assertSame(15000, $status->amount);
    }

    public function testErrorResponseThrowsWithGoPayErrors(): void
    {
        $client = new GoPayClient(new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800])),
            new MockResponse(
                json_encode(['errors' => [['error_code' => 111, 'error_name' => 'INVALID', 'field' => 'amount']]]),
                ['http_code' => 409],
            ),
        ]), 'cid', 'secret', '8925782195', self::API);

        try {
            $client->createPayment(['amount' => -1]);
            $this->fail('GoPayException expected');
        } catch (GoPayException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('amount', $e->getErrors()[0]['field']);
        }
    }

    public function testApiUrlFollowsEnvironmentUnlessSetExplicitly(): void
    {
        $this->assertStringStartsWith(GoPayClient::PRODUCTION_URL . '/', $this->firstRequestUrl('', 'prod'));
        $this->assertStringStartsWith(GoPayClient::SANDBOX_URL . '/', $this->firstRequestUrl('', 'dev'));
        $this->assertStringStartsWith(GoPayClient::SANDBOX_URL . '/', $this->firstRequestUrl('', 'test'));
        // e.g. a staging server running in prod environment with sandbox credentials
        $this->assertStringStartsWith(GoPayClient::SANDBOX_URL . '/', $this->firstRequestUrl(GoPayClient::SANDBOX_URL, 'prod'));
    }

    private function firstRequestUrl(string $apiUrl, string $environment): string
    {
        $urls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$urls) {
            $urls[] = $url;

            return str_ends_with($url, '/oauth2/token')
                ? new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 1800]))
                : new MockResponse(json_encode(['id' => 1, 'state' => 'CREATED']));
        });

        (new GoPayClient($httpClient, 'cid', 'secret', '8925782195', $apiUrl, $environment))->getStatus('1');

        return $urls[0];
    }

    public function testFailedTokenRequestThrows(): void
    {
        $client = new GoPayClient(new MockHttpClient([
            new MockResponse(json_encode(['errors' => [['error_code' => 202]]]), ['http_code' => 403]),
        ]), 'cid', 'wrong', '8925782195', self::API);

        $this->expectException(GoPayException::class);
        $this->expectExceptionMessage('oauth2/token');

        $client->getStatus('1');
    }
}
