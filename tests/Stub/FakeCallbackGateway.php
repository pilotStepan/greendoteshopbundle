<?php

namespace Greendot\EshopBundle\Tests\Stub;

use Throwable;
use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentResult;
use Greendot\EshopBundle\Service\PaymentGateway\PaymentCallbackGatewayInterface;

/**
 * Callback gateway whose resolveCallback() returns (or throws) whatever the test configured,
 * so the shared callback handling can be tested without any real gateway API.
 */
class FakeCallbackGateway implements PaymentCallbackGatewayInterface
{
    public function __construct(
        private PaymentResult|Throwable|null $result = null,
    ) {}

    public static function action(): PaymentTechnicalAction
    {
        return PaymentTechnicalAction::GOPAY;
    }

    public function getPayLink(Purchase $purchase): string
    {
        throw new \LogicException('FakeCallbackGateway::getPayLink() is not expected to be called');
    }

    public function verifyLink(): object
    {
        throw new \LogicException('FakeCallbackGateway::verifyLink() is not expected to be called');
    }

    public function resolveCallback(Request $request): PaymentResult
    {
        if ($this->result instanceof Throwable) {
            throw $this->result;
        }

        return $this->result ?? throw new \LogicException('FakeCallbackGateway::resolveCallback() was called without a configured result');
    }
}
