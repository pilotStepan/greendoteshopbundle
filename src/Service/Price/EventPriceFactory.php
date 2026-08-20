<?php

namespace Greendot\EshopBundle\Service\Price;

use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Event;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\PurchaseEvent;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\DiscountCombinationStrategyInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

class EventPriceFactory
{
    private int $afterRegistrationBonus;

    public function __construct(
        private Security                    $security,
        private PriceRepository             $priceRepository,
        private DiscountService             $discountService,
        private PriceUtils                  $priceUtils,
        private SettingsRepository          $settingsRepository,
        #[AutowireLocator('greendot_eshop.discount_combination_strategy', indexAttribute: 'key')]
        private ContainerInterface          $discountCombinationStrategies,
        #[Autowire(param: 'greendot_eshop.shop.price.extension.discount_combination_strategy')]
        private string                      $discountCombinationStrategyKey,
    ) {
        $this->afterRegistrationBonus = $this->settingsRepository->findParameterValueWithName('after_registration_discount') ?? 0;
    }

    private function discountCombinationStrategy(): DiscountCombinationStrategyInterface
    {
        if (!$this->discountCombinationStrategies->has($this->discountCombinationStrategyKey)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown discount combination strategy "%s". No service tagged "greendot_eshop.discount_combination_strategy" with key "%s" found.',
                $this->discountCombinationStrategyKey,
                $this->discountCombinationStrategyKey,
            ));
        }
        return $this->discountCombinationStrategies->get($this->discountCombinationStrategyKey);
    }

    public function create(
        Event|PurchaseEvent $event,
        Currency             $currency,
        VatCalculationType   $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType $discountCalculationType = DiscountCalculationType::WithDiscount,
        ?ConversionRate       $conversionRate = null,
    ): EventPrice
    {
        $purchase = null;
        if ($event instanceof PurchaseEvent and $event?->getPurchase()) {
            $purchase = $event->getPurchase();
        }

        if ($purchase) {
            if ($purchase->isVatExempted()) $vatCalculationType = VatCalculationType::WithoutVAT;
        }

        if (!$conversionRate) $conversionRate = $this->priceUtils->getConversionRate($currency, $purchase);

        return new EventPrice(
            $event,
            $currency,
            $conversionRate,
            $vatCalculationType,
            $discountCalculationType,
            $this->afterRegistrationBonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->discountCombinationStrategy(),
        );
    }

    public function entityLoad(
        Price                    $price,
        Currency                 $currency,
        VatCalculationType       $vatCalculationType = VatCalculationType::WithoutVAT,
        DiscountCalculationType  $discountCalculationType = DiscountCalculationType::WithDiscount,
        ?ConversionRate          $conversionRate = null,
    ): EventPrice
    {
        if (!$conversionRate) $conversionRate = $this->priceUtils->getConversionRate($currency);

        return new EventPrice(
            $price->getEvent(),
            $currency,
            $conversionRate,
            $vatCalculationType,
            $discountCalculationType,
            $this->afterRegistrationBonus,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->discountCombinationStrategy(),
            $price,
        );
    }
}
