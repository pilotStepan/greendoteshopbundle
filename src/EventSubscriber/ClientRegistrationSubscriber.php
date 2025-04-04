<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Greendot\EshopBundle\Entity\Project\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

readonly class ClientRegistrationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack             $requestStack,
        private EventDispatcherInterface $dispatcher
    )
    {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $client = $args->getObject();

        if (!$client instanceof Client || !$client->getPlainPassword()) {
            return;
        }

        $token = new UsernamePasswordToken(
            $client,
            'json_login',
            $client->getRoles()
        );

        $this->requestStack->getSession()->set('_security_json_login', serialize($token));

        $event = new InteractiveLoginEvent(
            $this->requestStack->getCurrentRequest(),
            $token
        );
        $this->dispatcher->dispatch($event, InteractiveLoginEvent::class);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::postPersist => 'postPersist',
        ];
    }
}