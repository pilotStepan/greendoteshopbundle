<?php

namespace Greendot\EshopBundle\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Processor for PurchaseDiscussion entities.
 *
 * Automatically sets the `isAdmin` flag based on user roles:
 * - Sets to `true` if the request comes from a user with ROLE_API (admin from CMS)
 * - Sets to `false` for normal website users
 *
 * This processor handles both standard authenticated sessions and API requests with JWT tokens.
 */
final readonly class PurchaseDiscussionProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface       $processor,
        private TokenStorageInterface    $tokenStorage,
        private RequestStack             $requestStack,
        private JWTTokenManagerInterface $jwtManager
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($data instanceof PurchaseDiscussion) {
            // First check if we have a token in storage (logged-in user)
            $token = $this->tokenStorage->getToken();
            $isAdmin = false;

            if ($token && in_array('ROLE_API', $token->getRoleNames(), true)) {
                $isAdmin = true;
            } else {
                // If no token in storage, check for JWT Bearer token in request
                $request = $this->requestStack->getCurrentRequest();
                $authHeader = $request?->headers->get('Authorization');

                if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                    try {
                        // Extract the token string
                        $jwtToken = substr($authHeader, 7);

                        // Use the parser to get the payload
                        $payload = $this->jwtManager->parse($jwtToken);

                        // Check if the token has ROLE_API
                        if (isset($payload['roles']) && in_array('ROLE_API', $payload['roles'], true)) {
                            $isAdmin = true;
                        }
                    } catch (\Exception $e) {
                        // Invalid token, isAdmin stays false
                    }
                }
            }

            $data->setIsAdmin($isAdmin);
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}