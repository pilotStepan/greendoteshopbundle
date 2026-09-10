<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Utils\StatelessRequestMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;


#[AsEventListener(event: KernelEvents::REQUEST, priority: 14)]
class CurrencyLocaleListener
{
    public function __construct(private CurrencyManager $currencyManager) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (StatelessRequestMatcher::isStateless($event->getRequest())) {
            return;
        }

        $this->currencyManager->setByLocale(
            $event->getRequest()->getLocale(),
        );
    }
}
