<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;

readonly class CheapTransportationStateProvider implements ProviderInterface
{
    public function __construct(
        private TransportationRepository $transportationRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Transportation|null
    {
        // TODO: fetch country
        $country = "CZ";
        return $this->transportationRepository->findOneByLowFree($country);
    }
}