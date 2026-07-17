<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Greendot\EshopBundle\Entity\Project\Transportation;


trait CzechPostHmacAuthTrait
{
    private function getTlsOptions(): array
    {
        return [
            'verify_peer' => false,
            'verify_host' => false,
        ];
    }

    private function getHeaders(Transportation $transportation, ?string $body): array
    {
        $timestamp = time();
        $nonce = $this->generateNonce();

        $headers = [
            'Api-Token' => $transportation->getToken(),
            'Authorization-Timestamp' => $timestamp,
            'Authorization' => $this->generateHmacAuth($transportation, $timestamp, $nonce, $body),
            'Content-Type' => 'application/json;charset=UTF-8',
        ];

        if ($body !== null) {
            $headers['Authorization-Content-SHA256'] = hash('sha256', $body);
        }

        return $headers;
    }

    private function generateNonce(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        );
    }

    private function generateHmacAuth(Transportation $transportation, int $timestamp, string $nonce, ?string $body): string
    {
        $contentSha256 = $body !== null ? hash('sha256', $body) : '';
        $stringToSign = "$contentSha256;$timestamp;$nonce";

        $secretKey = (string)$transportation->getSecretKey();
        $signature = hash_hmac('sha256', $stringToSign, $secretKey, true);
        $signatureBase64 = base64_encode($signature);

        return "CP-HMAC-SHA256 nonce=\"$nonce\", signature=\"$signatureBase64\"";
    }
}
