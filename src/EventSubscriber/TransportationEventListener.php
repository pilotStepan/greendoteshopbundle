<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyResolver;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;

/**
 * This class listens to postLoad events for Transportation and PaymentType entities
 * to set their prices based on the current purchase session.
 * Removes the need for manual handling price calculation on frontend.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Transportation::class)]
readonly class TransportationEventListener
{
    public function __construct(
        private EntityManagerInterface  $entityManager,
        private PurchasePriceFactory    $purchasePriceFactory,
        private ServiceCalculationUtils $serviceCalculationUtils,
        private CurrencyResolver        $currencyResolver,
    ) {}

    public function postLoad(Transportation $transportation): void
    {
        $currency = $this->currencyResolver->resolve();
        $cartEntity = $this->entityManager->getRepository(Purchase::class)->findOneBySession('purchase');
        $cart = $cartEntity ? clone $cartEntity : null;

        // Base price for the given service
        $basePrice = $this->serviceCalculationUtils->calculateServicePrice(
            $transportation,
            $currency,
            VatCalculationType::WithVAT,
        );

        // Price influenced by the current cart (if any)
        $cartPrice = $cart
            ? $this->purchasePriceFactory
            ->create(
                $cart->setTransportation($transportation), // Pass the transportation to the cart
                $currency,
                VatCalculationType::WithVAT
            )
            ->getTransportationPrice() ?? 0.0
            : $basePrice;

        // Free from price
        $freeFromPrice = $this->serviceCalculationUtils->getFreeFromPrice($transportation, $currency);

        $transportation
            ->setPrice($basePrice)
            ->setPriceForCart($cartPrice)
            ->setFreeFromPrice($freeFromPrice)
        ;
    }
}