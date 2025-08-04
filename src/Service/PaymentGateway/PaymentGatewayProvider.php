<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Provides access to payment gateway implementations based on payment actions.
 *
 * This class maintains a map of payment gateways, allowing retrieval by
 * technical action or by purchase. It is typically used to delegate payment
 * processing to the correct gateway implementation.
 */
class PaymentGatewayProvider
{
    /* Can be moved to .env or .yaml */
    const DEFAULT_GATEWAY = 'gpw';

    /** @var array<string, PaymentGatewayInterface> */
    private array $map = [];

    /**
     * @param iterable<PaymentGatewayInterface> $gateways
     */
    public function __construct(
        #[AutowireIterator('app.payment_gateway')]
        iterable $gateways
    )
    {
        /* @var PaymentGatewayInterface[] $gateways */
        foreach ($gateways as $gateway) {
            $this->map[$gateway::action()->value] = $gateway;
        }
    }

    public function get(PaymentTechnicalAction $type): ?PaymentGatewayInterface
    {
        return $this->map[$type->value] ?? null;
    }

    public function getByPurchase(Purchase $purchase): ?PaymentGatewayInterface
    {
        $action = $purchase->getPaymentType()?->getPaymentTechnicalAction();
        return $action ? $this->get($action) : null;
    }

    public function getDefault(): PaymentGatewayInterface
    {
        return $this->get(PaymentTechnicalAction::from(self::DEFAULT_GATEWAY));
    }
}