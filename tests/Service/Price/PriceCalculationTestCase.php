<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;

abstract class PriceCalculationTestCase extends TestCase
{
    protected $priceUtils;
    protected $priceRepository;
    protected $security;
    protected $discountService;
    protected $currencyRepository;
    protected $handlingPriceRepository;
    protected $productVariantPriceFactory;

    protected $settingsRepository;
    protected function setUp(): void
    {
        $this->priceUtils = new PriceUtils();
        $this->priceRepository = $this->createMock(PriceRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->discountService = $this->createMock(DiscountService::class);
        $this->settingsRepository = $this->createMock(SettingsRepository::class);
        $this->settingsRepository->method('findParameterValueWithName')->willReturn(20);

        $this->currencyRepository = $this->createMock(CurrencyRepository::class);
        $this->currencyRepository->method('findOneBy')
            ->with(['conversionRate' => 1])
            ->willReturn(FactoryUtil::czk());

        $this->handlingPriceRepository = $this->createMock(HandlingPriceRepository::class);
        $this->productVariantPriceFactory = new ProductVariantPriceFactory(
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->settingsRepository
        );
    }

    /**
     * Set up client discount for ProductVariant
     */
    protected function setupClientDiscountForProductVariant(float $clientDiscount): void
    {
        $client = $this->createMock(Client::class);
        $clientDiscountObject = $this->createMock(ClientDiscount::class);

        $clientDiscountObject->method('getDiscount')->willReturn($clientDiscount);
        $this->security->method('getUser')->willReturn($client);
        $this->discountService->method('getValidClientDiscount')->willReturn($clientDiscountObject);
    }

    /**
     * Create a ProductVariantPrice instance with all dependencies
     */
    protected function createProductVariantPrice(
        ProductVariant|PurchaseProductVariant $variant,
        int                                   $amount,
        Currency                              $currency,
        VatCalc                               $vatType,
        DiscCalc                              $discCalc
    ): ProductVariantPrice
    {
        return new ProductVariantPrice(
            $variant,
            $amount,
            $currency,
            $vatType,
            $discCalc,
            $this->settingsRepository,
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils
        );
    }
}