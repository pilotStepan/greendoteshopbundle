<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Greendot\EshopBundle\Utils\ApiRequestMatcher;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Controller\TurnOffIsActiveFilterController as ControllerTurnOffIsActiveFilterController;

readonly class BeforeControllerListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AffiliateService       $affiliateService,
    ) {}

    /**
     * @param ControllerEvent $event
     * @return void
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest() || ApiRequestMatcher::isApiRequest($event->getRequest())) {
            // never touch the session in sub-requests
            // do not proceed for API requests
            return;
        }

        $controller = is_array($event->getController())
            ? $event->getController()[0]
            : $event->getController();

        if ($controller instanceof ControllerTurnOffIsActiveFilterController) {
            $this->entityManager->getFilters()->disable('products_active');
            $this->entityManager->getFilters()->disable('variants_active');
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || ApiRequestMatcher::isApiRequest($event->getRequest())) {
            return;
        }
        $this->affiliateService->setAffiliateCookiesFromRequest($event);
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
