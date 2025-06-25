<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\PriceCalculator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This class listens to postLoad events for Transportation entity
 * to set their price based on the current purchase session.
 * FIXME: It uses the (DEPRECATED!) PriceCalculator service to calculate the price.
 * Removes the need for manual handling price calculation on frontend.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Transportation::class)]
readonly class TransportationEventListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PriceCalculator        $priceCalculator,
        private RequestStack           $requestStack,
    )
    {
    }

    public function postLoad(Transportation $entity): void
    {
        // If session currency not set, price calculator will use the default currency
        $currency = $this->requestStack->getSession()->get('currency');
        $cart = $this->entityManager->getRepository(Purchase::class)->findOneBySession('purchase');
        $cartPriceSource = $cart ?: $entity;

        $entity
            ->setPrice(
                $this->priceCalculator->transportationPrice($entity, VatCalculationType::WithVAT, $currency)
            )
            ->setPriceForCart(
                $this->priceCalculator->transportationPrice($cartPriceSource, VatCalculationType::WithVAT, $currency)
            )
        ;
    }
}