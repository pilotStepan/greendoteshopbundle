<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Bundle\SecurityBundle\Security;

class ClientStateProvider implements ProviderInterface
{
    public function __construct(private Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Client|null
    {
        $client = $this->security->getUser();
        if ($client instanceof Client) {
            return $client;
        } else {
            return null;
        }
    }
}