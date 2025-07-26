<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Client;
use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VoucherCalculationType as VouchCalc;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
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
            ->willReturn(FactoryUtil::czk())
        ;

        $this->handlingPriceRepository = $this->createMock(HandlingPriceRepository::class);
        $this->productVariantPriceFactory = new ProductVariantPriceFactory(
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils,
            $this->settingsRepository,
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
        Currency                              $currency,
        VatCalc                               $vatType,
        DiscCalc                              $discCalc,
        ?int                                  $amount,
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
            $this->priceUtils,
        );
    }

    protected function createPurchasePrice(
        Purchase $purchase,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
        Currency $currency,
    ): PurchasePrice
    {
        return new PurchasePrice(
            $purchase,
            $vatCalc,
            $discCalc,
            $currency,
            VouchCalc::WithoutVoucher,
            $this->productVariantPriceFactory,
            $this->currencyRepository,
            $this->handlingPriceRepository,
            $this->priceUtils,
        );
    }

    protected function createPurchase(array $ppv, float|null $clientDiscount, array|null $vouchers): Purchase&MockObject
    {
        $purchase = $this->createMock(Purchase::class);
        $purchaseProductVariants = [];
        $variantPrices = [];

        foreach ($ppv as $index => $variant) {
            $purchaseProductVariant = $this->createMock(PurchaseProductVariant::class);
            $productVariantMock = $this->createMock(ProductVariant::class);
            $productVariantMock->_index = $index;

            $purchaseProductVariant->method('getAmount')->willReturn($variant['amount']);
            $purchaseProductVariant->method('getProductVariant')->willReturn($productVariantMock);
            $purchaseProductVariant->method('getPurchase')->willReturn($purchase);

            $purchaseProductVariants[] = $purchaseProductVariant;
            $variantPrices[$index] = $variant['prices'];
        }

        $purchase->method('getProductVariants')->willReturn(new ArrayCollection($purchaseProductVariants));

        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->willReturnCallback(function ($productVariant) use ($variantPrices) {
                if (isset($productVariant->_index)) {
                    return $variantPrices[$productVariant->_index];
                }
                return null;
            })
        ;

        if ($clientDiscount) {
            $clientDiscountMock = $this->createMock(ClientDiscount::class);
            $clientDiscountMock->method('getDiscount')->willReturn($clientDiscount);
            $purchase->method('getClientDiscount')->willReturn($clientDiscountMock);
        }

        $purchase->method('getVouchersUsed')->willReturn(new ArrayCollection($vouchers ?? []));

        return $purchase;
    }
}