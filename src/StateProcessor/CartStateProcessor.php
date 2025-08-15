<?php

namespace Greendot\EshopBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * CartStateProcessor is responsible for processing the cart state on patch operation.
 * specifically, setting the branch if exactly one branch is associated with the transportation.
 */
class CartStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $inner,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Purchase
    {
        assert($data instanceof Purchase);

        $transportation = $data->getTransportation();

        // If the transportation is set and has exactly one branch, set that branch on the purchase.
        if ($transportation && $transportation->getBranches()->count() === 1) {
            $branch = $transportation->getBranches()->first();
            $data->setBranch($branch);
        }

        // If the transportation is unset, clear the branch on the purchase.
        if (!$transportation && $data->getBranch()) {
            $data->setBranch(null);
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}