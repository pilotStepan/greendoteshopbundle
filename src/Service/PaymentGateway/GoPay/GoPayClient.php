<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GoPay;

use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Thin client for the GoPay REST API.
 *
 * Handles the OAuth2 client-credentials token (cached in memory until shortly
 * before it expires) and the two calls the eshop needs: create a payment and
 * inquire its state.
 *
 * @see https://doc.gopay.com/
 */
class GoPayClient
{
    public const SANDBOX_URL = 'https://gw.sandbox.gopay.com/api';
    public const PRODUCTION_URL = 'https://gate.gopay.cz/api';

    private const TOKEN_SCOPE = 'payment-all';
    private const TOKEN_EXPIRY_MARGIN = 60;

    private ?string $token = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string              $clientId,
        private readonly string              $clientSecret,
        private readonly string              $goId,
        private readonly string              $apiUrl,
    ) {}

    public function getGoId(): int
    {
        return (int)$this->goId;
    }

    /**
     * @return array GoPay payment resource (contains `id`, `state`, `gw_url`, ...)
     * @throws GoPayException
     */
    public function createPayment(array $payment): array
    {
        return $this->request('POST', '/payments/payment', ['json' => $payment]);
    }

    /**
     * @throws GoPayException
     */
    public function getStatus(string $paymentId): GoPayPaymentStatus
    {
        $data = $this->request('GET', '/payments/payment/' . rawurlencode($paymentId));

        return GoPayPaymentStatus::fromResponse($data);
    }

    /**
     * @throws GoPayException
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options['auth_bearer'] = $this->getToken();
        $options['headers'] = ['Accept' => 'application/json'];

        return $this->decode(
            $this->httpClient->request($method, rtrim($this->apiUrl, '/') . $path, $options),
            sprintf('%s %s', $method, $path),
        );
    }

    /**
     * @throws GoPayException
     */
    private function getToken(): string
    {
        if ($this->token !== null && time() < $this->tokenExpiresAt) {
            return $this->token;
        }

        $data = $this->decode(
            $this->httpClient->request('POST', rtrim($this->apiUrl, '/') . '/oauth2/token', [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'headers' => ['Accept' => 'application/json'],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'scope' => self::TOKEN_SCOPE,
                ],
            ]),
            'POST /oauth2/token',
        );

        if (empty($data['access_token'])) {
            throw new GoPayException('GoPay token response does not contain an access_token.');
        }

        $this->token = $data['access_token'];
        $this->tokenExpiresAt = time() + (int)($data['expires_in'] ?? 0) - self::TOKEN_EXPIRY_MARGIN;

        return $this->token;
    }

    /**
     * @throws GoPayException
     */
    private function decode(ResponseInterface $response, string $operation): array
    {
        try {
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (Throwable $e) {
            throw new GoPayException(sprintf('GoPay %s failed: %s', $operation, $e->getMessage()), [], 0, $e);
        }

        if ($status >= 300) {
            $errors = $data['errors'] ?? [];
            throw new GoPayException(
                sprintf('GoPay %s returned HTTP %d: %s', $operation, $status, json_encode($errors ?: $data)),
                $errors,
                $status,
            );
        }

        return $data;
    }
}
