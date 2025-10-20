<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Service\ApiRequestDetector;
use Greendot\EshopBundle\Service\ListenerManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: 'kernel.controller', priority: -10)]
class AnonymousClientLogoutListener
{
    public function __construct(
        private Security            $security,
        private ListenerManager     $listenerManager,
        private ApiRequestDetector  $apiRequestDetector,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->supports($event)) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        if (!$user instanceof Client || !$user->isIsAnonymous()) {
            return;
        }

        if ($request->attributes->get('_route') !== 'client_section_order_detail') {
            $response = $this->security->logout(false);
            $event->setController(fn() => $response);
        }
    }

    public function supports($event) : bool
    {
        return $event->isMainRequest() && !$this->listenerManager->isDisabled(self::class) && !$this->apiRequestDetector->isApiRequest($event->getRequest());
    }
}
