<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Exception;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;

/**
 * Interface for payment gateway implementations.
 *
 * Implementations of this interface provide integration with specific payment systems.
 * Each gateway defines its supported technical action, generates payment links,
 * and verifies payment results.
 */
interface PaymentGatewayInterface
{
    /**
     * Returns the technical action associated with this payment gateway.
     *
     * @return PaymentTechnicalAction
     */
    public static function action(): PaymentTechnicalAction;

    /**
     * Generates a payment link for the given purchase.
     *
     * @param Purchase $purchase
     * @return string
     * @throws Exception
     */
    public function getPayLink(Purchase $purchase): string;

    /**
     * Verifies the payment result.
     *
     * @return object
     * @throws Exception
     */
    public function verifyLink(): object;
}