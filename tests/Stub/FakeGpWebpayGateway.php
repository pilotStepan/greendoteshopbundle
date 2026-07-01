<?php

namespace Greendot\EshopBundle\Tests\Stub;

use Throwable;
use Granam\GpWebPay\CardPayResponse;
use Greendot\EshopBundle\Service\PaymentGateway\GPWebpay;

/**
 * GPWebpay is a `readonly` class, so PHPUnit's createMock() cannot double it
 * (readonly classes can't be doubled) and its real verifyLink()/getPayLink()
 * pull in file-based RSA key material and real digest signing.
 *
 * This stub replaces the gateway call entirely: verifyLink() returns (or
 * throws) whatever the test configured, so controller tests can exercise
 * "gateway said X" without touching any real GPWebpay crypto.
 */
readonly class FakeGpWebpayGateway extends GPWebpay
{
    public function __construct(
        private CardPayResponse|Throwable|null $verifyResult = null,
        private string|Throwable|null $payLinkResult = null,
    ) {}

    public function verifyLink(): CardPayResponse
    {
        if ($this->verifyResult instanceof Throwable) {
            throw $this->verifyResult;
        }

        if ($this->verifyResult === null) {
            throw new \LogicException('FakeGpWebpayGateway::verifyLink() was called without a configured result');
        }

        return $this->verifyResult;
    }

    public function getPayLink(\Greendot\EshopBundle\Entity\Project\Purchase $purchase): string
    {
        if ($this->payLinkResult instanceof Throwable) {
            throw $this->payLinkResult;
        }

        if ($this->payLinkResult === null) {
            throw new \LogicException('FakeGpWebpayGateway::getPayLink() was called without a configured result');
        }

        return $this->payLinkResult;
    }
}
