<?php

namespace Greendot\EshopBundle\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsMonologProcessor]
final readonly class UserContextProcessor
{
    public function __construct(
        #[Autowire('@security.token_storage')]
        private TokenStorageInterface $tokenStorage,
        #[Autowire('@request_stack')]
        private RequestStack          $requestStack,
    ) {}

    public function __invoke(array $record): array
    {
        $token = $this->tokenStorage->getToken();
        $userId = $token?->getUser()?->getId();
        $current = $this->requestStack->getCurrentRequest();
        $clientIp = $current?->getClientIp();
        $requestMethod = $current?->getMethod();
        $requestUri = $current?->getUri();

        $record['extra'] += [
            'user_id' => $userId,
            'client_ip' => $clientIp,
            'request_uri' => $requestUri,
            'request_method' => $requestMethod,
        ];

        return $record;
    }
}
