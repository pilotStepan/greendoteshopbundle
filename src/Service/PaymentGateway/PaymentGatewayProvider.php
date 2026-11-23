<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\PaymentTechnicalAction;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    /** @var array<string, PaymentGatewayInterface> */
    private array $map = [];

    /**
     * @param iterable<PaymentGatewayInterface> $gateways
     * @param string $defaultGateway
     */
    public function __construct(
        #[AutowireIterator('app.payment_gateway')]
        iterable                $gateways,
        #[Autowire(param: 'greendot_eshop.payment.default_gateway')]
        private readonly string $defaultGateway = 'gpw',
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
        return $this->get(PaymentTechnicalAction::from($this->defaultGateway));
    }

    /**
     * Gateway handling its callbacks through the shared return/notify routes, null when it does not (e.g. GP webpay).
     */
    public function getCallbackGateway(PaymentTechnicalAction $type): ?PaymentCallbackGatewayInterface
    {
        $gateway = $this->get($type);

        return $gateway instanceof PaymentCallbackGatewayInterface ? $gateway : null;
    }
}
