<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\PriceCalculator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This class listens to postLoad events for PaymentType entity
 * to set their price based on the current purchase session.
 * FIXME: It uses the (DEPRECATED!) PriceCalculator service to calculate the price.
 * Removes the need for manual handling price calculation on frontend.
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: PaymentType::class)]
readonly class PaymentTypeEventListener
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PriceCalculator        $priceCalculator,
        private RequestStack           $requestStack,
    )
    {
    }

    public function postLoad(PaymentType $entity): void
    {
        // If session currency not set, price calculator will use the default currency
        $currency = $this->requestStack->getSession()->get('currency');
        $cart = $this->entityManager->getRepository(Purchase::class)->findOneBySession('purchase');
        $cartPriceSource = $cart ?: $entity;

        $entity
            ->setPrice(
                $this->priceCalculator->paymentPrice($entity, VatCalculationType::WithVAT, $currency)
            )
            ->setPriceForCart(
                $this->priceCalculator->paymentPrice($cartPriceSource, VatCalculationType::WithVAT, $currency)
            )
        ;
    }
}