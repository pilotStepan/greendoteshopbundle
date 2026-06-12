<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CalculatedPricesServiceTest extends TestCase
{
    private MockObject $productVariantPriceFactory;
    private MockObject $purchasePriceFactory;
    private MockObject $currencyManager;
    private MockObject $priceRepository;
    private CalculatedPricesService $service;

    protected function setUp(): void
    {
        $this->productVariantPriceFactory = $this->createMock(ProductVariantPriceFactory::class);
        $this->purchasePriceFactory = $this->createMock(PurchasePriceFactory::class);
        $this->currencyManager = $this->createMock(CurrencyManager::class);
        $this->priceRepository = $this->createMock(PriceRepository::class);

        $this->currencyManager->method('get')->willReturn(FactoryUtil::czk());

        $this->service = new CalculatedPricesService(
            $this->productVariantPriceFactory,
            $this->purchasePriceFactory,
            $this->currencyManager,
            $this->priceRepository,
        );
    }

    /**
     * Build a mock ProductVariantPrice whose getPiecePrice() returns different values
     * depending on the last VatCalculationType + DiscountCalculationType combination set.
     *
     * @param array<string, float> $priceMap keys: "vat|disc" using enum values, values: price
     */
    private function makeProductVariantPriceMock(array $priceMap): MockObject
    {
        $pvPrice = $this->createMock(ProductVariantPrice::class);
        $vatType = null;
        $discType = null;

        $pvPrice->method('setVatCalculationType')
            ->willReturnCallback(function (VatCalc $v) use (&$vatType, $pvPrice) {
                $vatType = $v;
                return $pvPrice;
            });
        $pvPrice->method('setDiscountCalculationType')
            ->willReturnCallback(function (DiscCalc $v) use (&$discType, $pvPrice) {
                $discType = $v;
                return $pvPrice;
            });
        $pvPrice->method('setAmount')->willReturnSelf();
        $pvPrice->method('getPiecePrice')
            ->willReturnCallback(function () use (&$vatType, &$discType, $priceMap) {
                $key = ($vatType?->value ?? '') . '|' . ($discType?->value ?? '');
                return $priceMap[$key] ?? 0.0;
            });

        return $pvPrice;
    }

    /**
     * Build a mock PurchasePrice whose getPrice() returns different values
     * depending on the last VatCalculationType + DiscountCalculationType set,
     * and whether services are included.
     *
     * @param array<string, array{true: float, false: float}> $priceMap
     */
    private function makePurchasePriceMock(array $priceMap): MockObject
    {
        $ppPrice = $this->createMock(PurchasePrice::class);
        $vatType = null;
        $discType = null;

        $ppPrice->method('setVatCalculationType')
            ->willReturnCallback(function (VatCalc $v) use (&$vatType, $ppPrice) {
                $vatType = $v;
                return $ppPrice;
            });
        $ppPrice->method('setDiscountCalculationType')
            ->willReturnCallback(function (DiscCalc $v) use (&$discType, $ppPrice) {
                $discType = $v;
                return $ppPrice;
            });
        $ppPrice->method('getPrice')
            ->willReturnCallback(function (bool $withServices = false) use (&$vatType, &$discType, $priceMap) {
                $key = ($vatType?->value ?? '') . '|' . ($discType?->value ?? '');
                return $priceMap[$key][$withServices] ?? 0.0;
            });

        return $ppPrice;
    }

    private function defaultVariantPriceMap(): array
    {
        return [
            VatCalc::WithVAT->value    . '|' . DiscCalc::WithDiscount->value    => 121.0,
            VatCalc::WithoutVAT->value . '|' . DiscCalc::WithDiscount->value    => 100.0,
            VatCalc::WithVAT->value    . '|' . DiscCalc::WithoutDiscount->value => 130.0,
            VatCalc::WithoutVAT->value . '|' . DiscCalc::WithoutDiscount->value => 110.0,
        ];
    }

    private function defaultPurchasePriceMap(): array
    {
        return [
            VatCalc::WithVAT->value    . '|' . DiscCalc::WithDiscount->value    => [true => 221.0, false => 121.0],
            VatCalc::WithoutVAT->value . '|' . DiscCalc::WithDiscount->value    => [true => 200.0, false => 100.0],
            VatCalc::WithVAT->value    . '|' . DiscCalc::WithoutDiscount->value => [true => 231.0, false => 131.0],
            VatCalc::WithoutVAT->value . '|' . DiscCalc::WithoutDiscount->value => [true => 210.0, false => 110.0],
        ];
    }

    // -------------------------------------------------------------------------
    // makeCalculatedPricesForProductVariant
    // -------------------------------------------------------------------------

    public function testMakeCalculatedPricesForProductVariantReturnsEarlyWhenAlreadyCalculated(): void
    {
        $variant = new ProductVariant();
        $variant->setCalculatedPrices([1 => ['priceVat' => 99.0]]);

        $this->productVariantPriceFactory->expects($this->never())->method('createFromContext');

        $result = $this->service->makeCalculatedPricesForProductVariant($variant);

        $this->assertSame($variant, $result);
        $this->assertEquals([1 => ['priceVat' => 99.0]], $result->getCalculatedPrices());
    }

    public function testMakeCalculatedPricesForProductVariantBuildsMatrixPerAmount(): void
    {
        $variant = new ProductVariant();
        $pvPrice = $this->makeProductVariantPriceMock($this->defaultVariantPriceMap());

        $this->productVariantPriceFactory->method('createFromContext')->willReturn($pvPrice);
        $this->priceRepository->method('getUniqueMinimalAmounts')->willReturn([1, 5]);

        $result = $this->service->makeCalculatedPricesForProductVariant($variant);

        $calculatedPrices = $result->getCalculatedPrices();
        $this->assertArrayHasKey(1, $calculatedPrices);
        $this->assertArrayHasKey(5, $calculatedPrices);
        $this->assertEqualsWithDelta(121.0, $calculatedPrices[1]['priceVat'], 0.001);
        $this->assertEqualsWithDelta(100.0, $calculatedPrices[1]['priceNoVat'], 0.001);
        $this->assertEqualsWithDelta(130.0, $calculatedPrices[1]['priceVatNoDiscount'], 0.001);
        $this->assertEqualsWithDelta(110.0, $calculatedPrices[1]['priceNoVatNoDiscount'], 0.001);
    }

    // -------------------------------------------------------------------------
    // makeCalculatedPricesForProduct
    // -------------------------------------------------------------------------

    public function testMakeCalculatedPricesForProductReturnsEarlyWhenAlreadyCalculated(): void
    {
        $product = new Product();
        $product->setCalculatedPrices(['priceVat' => 50.0]);

        $this->productVariantPriceFactory->expects($this->never())->method('entityLoadFromContext');

        $result = $this->service->makeCalculatedPricesForProduct($product);

        $this->assertSame($product, $result);
    }

    public function testMakeCalculatedPricesForProductSkipsWhenNoCheapestPrice(): void
    {
        $product = new Product();
        $this->priceRepository->method('findCheapestPriceForProduct')->willReturn(null);

        $result = $this->service->makeCalculatedPricesForProduct($product);

        $this->assertSame($product, $result);
        $this->assertEmpty($result->getCalculatedPrices());
    }

    public function testMakeCalculatedPricesForProductSkipsWhenFactoryReturnsNull(): void
    {
        $product = new Product();
        $cheapestPrice = new Price();

        $this->priceRepository->method('findCheapestPriceForProduct')->willReturn($cheapestPrice);
        $this->productVariantPriceFactory->method('entityLoadFromContext')->willReturn(null);

        $result = $this->service->makeCalculatedPricesForProduct($product);

        $this->assertEmpty($result->getCalculatedPrices());
    }

    public function testMakeCalculatedPricesForProductBuildsMatrix(): void
    {
        $product = new Product();
        $cheapestPrice = new Price();
        $pvPrice = $this->makeProductVariantPriceMock($this->defaultVariantPriceMap());

        $this->priceRepository->method('findCheapestPriceForProduct')->willReturn($cheapestPrice);
        $this->productVariantPriceFactory->method('entityLoadFromContext')->willReturn($pvPrice);

        $result = $this->service->makeCalculatedPricesForProduct($product);

        $calculatedPrices = $result->getCalculatedPrices();
        $this->assertNotEmpty($calculatedPrices);
        $this->assertEqualsWithDelta(121.0, $calculatedPrices['priceVat'], 0.001);
        $this->assertEqualsWithDelta(100.0, $calculatedPrices['priceNoVat'], 0.001);
        $this->assertEqualsWithDelta(130.0, $calculatedPrices['priceVatNoDiscount'], 0.001);
        $this->assertEqualsWithDelta(110.0, $calculatedPrices['priceNoVatNoDiscount'], 0.001);
    }

    public function testMakeCalculatedPricesForProductUsesCheapestPriceArgument(): void
    {
        $product = new Product();
        $providedPrice = new Price();
        $pvPrice = $this->makeProductVariantPriceMock($this->defaultVariantPriceMap());

        $this->productVariantPriceFactory
            ->expects($this->once())
            ->method('entityLoadFromContext')
            ->with($providedPrice)
            ->willReturn($pvPrice);

        $this->service->makeCalculatedPricesForProduct($product, null, $providedPrice);

        $this->assertNotEmpty($product->getCalculatedPrices());
    }

    // -------------------------------------------------------------------------
    // makeCalculatedPricesForPurchase
    // -------------------------------------------------------------------------

    public function testMakeCalculatedPricesForPurchaseReturnsEarlyWhenAlreadyCalculated(): void
    {
        $purchase = new Purchase();
        $purchase->setCalculatedPrices(['priceVat' => 200.0]);

        $this->purchasePriceFactory->expects($this->never())->method('create');

        $result = $this->service->makeCalculatedPricesForPurchase($purchase);

        $this->assertSame($purchase, $result);
    }

    public function testMakeCalculatedPricesForPurchaseBuildsMatrix(): void
    {
        $purchase = new Purchase();
        $ppPrice = $this->makePurchasePriceMock($this->defaultPurchasePriceMap());

        $this->purchasePriceFactory->method('create')->willReturn($ppPrice);

        $result = $this->service->makeCalculatedPricesForPurchase($purchase);

        $calculatedPrices = $result->getCalculatedPrices();
        $this->assertNotEmpty($calculatedPrices);
        $this->assertEqualsWithDelta(221.0, $calculatedPrices['priceVat'], 0.001);
        $this->assertEqualsWithDelta(200.0, $calculatedPrices['priceNoVat'], 0.001);
        $this->assertEqualsWithDelta(231.0, $calculatedPrices['priceVatNoDiscount'], 0.001);
        $this->assertEqualsWithDelta(210.0, $calculatedPrices['priceNoVatNoDiscount'], 0.001);
        $this->assertEqualsWithDelta(121.0, $calculatedPrices['priceVatNoServices'], 0.001);
        $this->assertEqualsWithDelta(100.0, $calculatedPrices['priceNoVatNoServices'], 0.001);
        $this->assertEqualsWithDelta(131.0, $calculatedPrices['priceVatNoDiscountNoServices'], 0.001);
        $this->assertEqualsWithDelta(110.0, $calculatedPrices['priceNoVatNoDiscountNoServices'], 0.001);
    }

    // -------------------------------------------------------------------------
    // makeCalculatedPricesForPurchaseProductVariant
    // -------------------------------------------------------------------------

    public function testMakeCalculatedPricesForPurchaseProductVariantReturnsEarlyWhenAlreadyCalculated(): void
    {
        $ppv = new PurchaseProductVariant();
        $ppv->setCalculatedPrices(['priceVat' => 50.0]);

        $this->productVariantPriceFactory->expects($this->never())->method('createFromContext');

        $result = $this->service->makeCalculatedPricesForPurchaseProductVariant($ppv);

        $this->assertSame($ppv, $result);
    }

    public function testMakeCalculatedPricesForPurchaseProductVariantBuildsMatrix(): void
    {
        $ppv = new PurchaseProductVariant();
        $pvPrice = $this->makeProductVariantPriceMock($this->defaultVariantPriceMap());

        $this->productVariantPriceFactory->method('createFromContext')->willReturn($pvPrice);

        $result = $this->service->makeCalculatedPricesForPurchaseProductVariant($ppv);

        $calculatedPrices = $result->getCalculatedPrices();
        $this->assertNotEmpty($calculatedPrices);
        $this->assertEqualsWithDelta(121.0, $calculatedPrices['priceVat'], 0.001);
        $this->assertEqualsWithDelta(100.0, $calculatedPrices['priceNoVat'], 0.001);
    }
}
