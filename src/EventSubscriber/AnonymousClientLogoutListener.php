<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class AnonymousClientLogoutListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private LogoutUrlGenerator $logoutUrlGenerator,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Skip sub-requests (e.g. when rendering fragments)
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();

        // If not a anonymous Client entity → ignore
        if (!$user instanceof Client || !$user->isIsAnonymous()) {
            return;
        }

        // Allow only the order detail route
        if ($request->attributes->get('_route') !== 'client_section_order_detail') {
            // Build the logout URL for your "main" firewall
            $logoutUrl = $this->logoutUrlGenerator->getLogoutPath('main');

            // Redirect immediately to logout
            $event->setResponse(new RedirectResponse($logoutUrl));
        }
    }
}
