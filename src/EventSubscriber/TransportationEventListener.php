<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Service\CurrencyResolver;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;

/**
 * This class listens to postLoad events for Transportation entity
 * to set its prices based on the current purchase session.
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
        $cartSession = $this->entityManager
            ->getRepository(Purchase::class)
            ->findOneBySession('purchase')
        ;

        // don't mutate the original, shallow clone is enough
        $cart = $cartSession ? clone $cartSession : null;

        $basePrice = $this->serviceCalculationUtils->calculateServicePrice(
            $transportation,
            $currency,
            VatCalculationType::WithVAT,
        );
        $freeFromPrice = $this->serviceCalculationUtils->getFreeFromPrice($transportation, $currency);

        [$cartPrice, $amountUntilFree] = $this->resolveCartPriceAndThreshold(
            $cart,
            $transportation,
            $currency,
            $basePrice,
            $freeFromPrice,
        );

        $transportation
            ->setPrice($basePrice)
            ->setPriceForCart($cartPrice)
            ->setFreeFromPrice($freeFromPrice)
            ->setAmountUntilFree($amountUntilFree)
        ;
    }

    /**
     * Computes the price to display in the context of the current cart
     * and how much is left until transportation becomes free.
     *
     * @return array{0: float, 1: float|null} [cartPrice, amountUntilFree]
     */
    private function resolveCartPriceAndThreshold(
        ?Purchase      $cart,
        Transportation $transportation,
        Currency       $currency,
        float          $basePrice,
        ?float         $freeFromPrice,
    ): array
    {
        if ($cart === null) {
            return [$basePrice, $freeFromPrice];
        }

        $priceCalc = $this->purchasePriceFactory->create(
            $cart->setTransportation($transportation), // pass our transportation to the cart
            $currency,
            VatCalculationType::WithVAT,
        );

        $cartTransportationPrice = $priceCalc->getTransportationPrice() ?? 0.0;
        $cartSubtotalExServices = $priceCalc->getPrice();

        // If there is no free-from rule, null signals "not applicable".
        $amountUntilFree = $this->calculateAmountUntilFree($freeFromPrice, $cartSubtotalExServices);

        return [$cartTransportationPrice, $amountUntilFree];
    }

    private function calculateAmountUntilFree(?float $freeFromPrice, float $cartSubtotalExSvcs): ?float
    {
        // No free-from -> not applicable
        if ($freeFromPrice === null) {
            return null;
        }

        return max(0.0, $freeFromPrice - $cartSubtotalExSvcs);
    }
}