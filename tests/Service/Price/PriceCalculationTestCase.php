<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class PriceCalculationTestCase extends TestCase
{
    private $priceUtils;
    private $priceRepository;
    private $security;
    private $discountService;

    protected function setUp(): void
    {
        $this->priceUtils = new PriceUtils();
        $this->priceRepository = $this->createMock(PriceRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->discountService = $this->createMock(DiscountService::class);
    }

    /**
     * Create a variant based on a product type
     */
    protected function createVariant(string $productType, int $amount, array $prices, ?float $clientDiscount): object
    {
        $variant = match ($productType) {
            'pv' => $this->createProductVariantMock($clientDiscount),
            'ppv' => $this->createPurchaseProductVariantMock($amount, $clientDiscount),
        };
        $this->priceRepository->method('findPricesByDateAndProductVariantNew')
            ->with($variant)
            ->willReturn($prices);

        return $variant;
    }

    /**
     * Create a ProductVariant mock with client discount if needed
     */
    private function createProductVariantMock(?float $clientDiscount): ProductVariant
    {
        $variant = $this->createMock(ProductVariant::class);

        if ($clientDiscount !== null) {
            $this->setupClientDiscountForProductVariant($clientDiscount);
        } else {
            $this->security->method('getUser')->willReturn(null);
        }

        return $variant;
    }

    /**
     * Create a PurchaseProductVariant mock with necessary dependencies
     */
    private function createPurchaseProductVariantMock(int $amount, ?float $clientDiscount): PurchaseProductVariant
    {
        $variant = $this->createMock(PurchaseProductVariant::class);
        $productVariantMock = $this->createMock(ProductVariant::class);

        $variant->method('getProductVariant')->willReturn($productVariantMock);
        $variant->method('getAmount')->willReturn($amount);

        if ($clientDiscount !== null) {
            $purchase = $this->createMock(Purchase::class);
            $clientDiscountObj = $this->createMock(ClientDiscount::class);

            $clientDiscountObj->method('getDiscount')->willReturn($clientDiscount);
            $purchase->method('getClientDiscount')->willReturn($clientDiscountObj);
            $variant->method('getPurchase')->willReturn($purchase);
        }

        return $variant;
    }

    /**
     * Set up client discount for ProductVariant
     */
    private function setupClientDiscountForProductVariant(float $clientDiscount): void
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
            $this->security,
            $this->priceRepository,
            $this->discountService,
            $this->priceUtils
        );
    }
}