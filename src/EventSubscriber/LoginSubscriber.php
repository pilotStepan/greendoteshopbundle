<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

readonly class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PurchaseRepository $purchaseRepository,
        private RequestStack       $requestStack,
        private EntityManagerInterface $entityManager,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onInteractiveLogin',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $session = $this->requestStack->getSession();
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof Client) {
            return;
        }

        $sessionCart = $this->purchaseRepository->findOneBySession('purchase');
        $clientCart = $this->purchaseRepository->findCartForClient($user);

        // Skip if carts are identical
        if ($sessionCart && $clientCart && $sessionCart->getId() === $clientCart->getId()) return;

        // Case 1: Session cart has items - use it as primary, remove existing client cart
        if ($sessionCart && !$sessionCart->getProductVariants()->isEmpty()) {
            if ($clientCart) {
                $this->purchaseRepository->remove($clientCart);
            }

            $user->addOrder($sessionCart);
            $sessionCart->setClient($user);
            $this->entityManager->flush();
            return;
        }

        // Case 2: Only client cart exists - update session reference
        if ($clientCart) {
            $session->set('purchase', $clientCart->getId());
        }
    }
}