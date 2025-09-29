<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class ClientStateProvider implements ProviderInterface
{
    public function __construct(
        private Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Client
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new HttpException(Response::HTTP_NO_CONTENT);
        }

        return $client;
    }
}