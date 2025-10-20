<?php
// src/EventSubscriber/DisableListenerSubscriber.php
namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Attribute\DisableListeners;
use Greendot\EshopBundle\Service\ListenerManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use ReflectionMethod;

/**
 * Disables listeners for controllers with the DisableListeners attribute
 */
class DisableListenersSubscriber implements EventSubscriberInterface
{
    public function __construct(private ListenerManager $listenerManager) {}

    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return; 
        }

        [$controllerObject, $methodName] = $controller;

        $reflectionMethod = new ReflectionMethod($controllerObject, $methodName);
        $attributes = $reflectionMethod->getAttributes(DisableListeners::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $this->listenerManager->disableAll($instance->listenerClasses);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
