<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Greendot\EshopBundle\Controller\TurnOffIsActiveFilterController as ControllerTurnOffIsActiveFilterController;

class BeforeControllerListener implements EventSubscriberInterface
{
    private const API_PREFIXES = [
        '/shop/api',
        '/api',
        '/simple/api',
        '/my-api',
        '/translate',
        '/_fragment',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrencyRepository     $currencyRepository,
        private readonly AffiliateService       $affiliateService,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->isApiRequest($event->getRequest())) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (!$session->has('selectedCurrency')) {
            $session->set(
                'selectedCurrency',
                $this->currencyRepository->findOneBy(['isDefault' => true]),
            );
        }

    }

    /**
     * @param ControllerEvent $event
     * @return void
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest() || $this->isApiRequest($event->getRequest())) {
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
        if (!$event->isMainRequest() || $this->isApiRequest($event->getRequest())) {
            return;
        }
        $this->affiliateService->setAffiliateCookiesFromRequest($event);
    }

    private function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        foreach (self::API_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
