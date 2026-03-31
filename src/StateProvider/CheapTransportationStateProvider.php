<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class CheapTransportationStateProvider implements ProviderInterface
{
    public function __construct(
        private TransportationRepository    $transportationRepository,
        private RequestStack                $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Transportation|null
    {
        $request = $context['request'] ?? null;

        if (!$request) {
            $country = 'CZ'; 
        } else {
            $country = $request->query->get('country', 'CZ');
        }
        return $this->transportationRepository->findOneByLowFree($country);
    }
}