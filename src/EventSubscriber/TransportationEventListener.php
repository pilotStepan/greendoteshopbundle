<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;
use Symfony\Component\HttpFoundation\RequestStack;

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
        private RequestStack            $requestStack,
        private PurchasePriceFactory    $purchasePriceFactory,
        private ServiceCalculationUtils $serviceCalculationUtils,
    )
    {
    }

    public function postLoad(Transportation $transportation): void
    {
        $currency = $this->resolveCurrency();
        $cart = $this->entityManager->getRepository(Purchase::class)->findOneBySession('purchase');

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

        $transportation
            ->setPrice($basePrice)
            ->setPriceForCart($cartPrice);
    }

    /**
     * Returns the currency selected in session (if set and valid),
     * otherwise falls back to the default (conversion rate = 1).
     */
    private function resolveCurrency(): Currency
    {
        $sessionCurrency = $this->requestStack->getSession()->get('currency');

        if ($sessionCurrency instanceof Currency) {
            return $sessionCurrency;
        }

        /** @var Currency $defaultCurrency */
        $defaultCurrency = $this->entityManager
            ->getRepository(Currency::class)
            ->findOneBy(['conversionRate' => 1]);

        return $defaultCurrency;
    }
}