<?php
declare(strict_types=1);

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\HandlingPrice;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;

readonly class ServiceCalculationUtils
{
    public function __construct(
        private HandlingPriceRepository $handlingPriceRepository,
        private PriceUtils              $priceUtils,
    )
    {
    }

    /**
     * Calculate handling (service) price
     */
    public function calculateServicePrice(
        Transportation|PaymentType $service,
        Currency                   $currency,
        VatCalculationType         $vatCalculationType = VatCalculationType::WithoutVAT,
        float                      $theoreticalAmount = 0.0,
    ): float
    {
        $handlingPrice = $this->handlingPriceRepository->getByDate($service);

        if ($this->isFreeHandling($handlingPrice, $theoreticalAmount)) return 0.0;

        $basePrice = $handlingPrice->getPrice();
        $vatAmount = $this->priceUtils->calculatePercentage($basePrice, $handlingPrice->getVat());

        $price = match ($vatCalculationType) {
            VatCalculationType::WithoutVAT => $basePrice,
            VatCalculationType::WithVAT => $basePrice + $vatAmount,
            VatCalculationType::OnlyVAT => $vatAmount,
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported VAT calculation type "%s"', $vatCalculationType->name)
            ),
        };

        return $this->priceUtils->convertCurrency($price, $currency);
    }

    /**
     * Handling is free when:
     *  • there is no price record,
     *  • the price is < 1,
     *  • or the “theoretical” basket amount grants free shipping.
     */
    private function isFreeHandling(?HandlingPrice $price, float $theoreticalAmount): bool
    {
        return !$price || $price->getPrice() < 1 || $theoreticalAmount >= $price->getFreeFromPrice();
    }

    /**
     * @param Transportation|PaymentType $service
     * @param Currency $currency
     * @return float - the total purchase price threshold for the given service to be free
     */
    public function getFreeFromPrice(
        Transportation|PaymentType  $service,
        Currency                    $currency,
    ): float
    {
        $handlingPrice = $this->handlingPriceRepository->getByDate($service);
        $freeFromPrice = $handlingPrice->getFreeFromPrice();
        return $this->priceUtils->convertCurrency($freeFromPrice, $currency);
    }


}