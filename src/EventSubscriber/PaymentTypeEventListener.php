<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This class listens to postLoad events for Transportation and PaymentType entities
 * to set their prices based on the current purchase session.
 * Removes the need for manual handling price calculation on frontend.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: PaymentType::class)]
readonly class PaymentTypeEventListener
{
    public function __construct(
        private EntityManagerInterface  $entityManager,
        private RequestStack            $requestStack,
        private PurchasePriceFactory    $purchasePriceFactory,
        private ServiceCalculationUtils $serviceCalculationUtils,
    )
    {
    }

    public function postLoad(PaymentType $paymentType): void
    {
        $currency = $this->resolveCurrency();
        $cart = $this->entityManager->getRepository(Purchase::class)->findOneBySession('purchase');

        // Base price for the given service
        $basePrice = $this->serviceCalculationUtils->calculateServicePrice(
            $paymentType,
            $currency,
            VatCalculationType::WithVAT,
        );

        // Price influenced by the current cart (if any)
        $cartPrice = $cart
            ? $this->purchasePriceFactory
                ->create(
                    $cart->setPaymentType($paymentType), // Pass the payment type to the cart
                    $currency,
                    VatCalculationType::WithVAT
                )
                ->getPaymentPrice() ?? 0.0
            : $basePrice;

        $paymentType
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