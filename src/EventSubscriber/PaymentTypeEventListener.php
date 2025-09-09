<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\CurrencyResolver;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;

/**
 * This class listens to postLoad events for PaymentType entity
 * to set its prices based on the current purchase session.
 * Removes the need for manual handling price calculation on frontend.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: PaymentType::class)]
readonly class PaymentTypeEventListener
{
    public function __construct(
        private EntityManagerInterface  $entityManager,
        private PurchasePriceFactory    $purchasePriceFactory,
        private ServiceCalculationUtils $serviceCalculationUtils,
        private CurrencyResolver        $currencyResolver,
    ) {}

    public function postLoad(PaymentType $paymentType): void
    {
        $currency = $this->currencyResolver->resolve();
        $cartEntity = $this->entityManager->getRepository(Purchase::class)->findOneBySession('purchase');
        $cart = $cartEntity ? clone $cartEntity : null;

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
            ->setPriceForCart($cartPrice)
        ;
    }
}