<?php

namespace Greendot\EshopBundle\Logging;

use Monolog\LogRecord;
use Monolog\Attribute\AsMonologProcessor;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\InMemoryUser;
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

    public function __invoke(LogRecord $record): LogRecord
    {
        $current = $this->requestStack->getCurrentRequest();

        [$userId, $isAdmin] = $this->extractUserContext();

        $record->extra += [
            'is_admin' => $isAdmin,
            'client_id' => $userId,
            'client_ip' => $current?->getClientIp(),
            'request_uri' => $current?->getUri(),
            'request_method' => $current?->getMethod(),
        ];

        return $record;
    }

    /**
     * @return array{0: int|null, 1: bool}
     */
    private function extractUserContext(): array
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        // Authenticated client
        if ($user instanceof Client) {
            return [$user->getId(), false];
        }

        // Simple admin
        if ($user instanceof InMemoryUser && $user->getRoles() === ['ROLE_API']) {
            return [null, true];
        }

        // No user
        return [null, false];
    }
}
