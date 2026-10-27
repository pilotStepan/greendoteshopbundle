<?php

namespace Greendot\EshopBundle\Tests\DataLayer\Factory;

use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\DataLayer\Factory\DataLayerItemFactory;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\ProductVariantPrice;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use PHPUnit\Framework\TestCase;

class DataLayerItemFactoryTest extends TestCase
{
    public function testCreateFromVariantReturnsUnitPricesForMultiplePieces(): void
    {
        $factory = $this->createFactory(lineNoVat: 4132.24, lineVat: 5000.0, minimalAmount: 1);

        $item = $factory->createFromVariant($this->createVariant(), $this->createMock(Currency::class), 2);

        self::assertSame(2, $item->quantity);
        self::assertSame(2500.0, $item->priceVat);
        self::assertSame(2066.12, $item->priceNoVat);
    }

    public function testCreateFromVariantDividesByMinimalAmountWhenQuantityIsMissing(): void
    {
        $factory = $this->createFactory(lineNoVat: 300.0, lineVat: 363.0, minimalAmount: 3);

        $item = $factory->createFromVariant($this->createVariant(), $this->createMock(Currency::class));

        self::assertSame(3, $item->quantity);
        self::assertSame(121.0, $item->priceVat);
    }

    public function testSerializedItemContainsGa4PriceKey(): void
    {
        $factory = $this->createFactory(lineNoVat: 4132.24, lineVat: 5000.0, minimalAmount: 1);

        $json = $factory->createFromVariant($this->createVariant(), $this->createMock(Currency::class), 2)->jsonSerialize();

        self::assertSame(2500.0, $json['price']);
        self::assertSame($json['price'], $json['priceVat']);
    }

    private function createFactory(float $lineNoVat, float $lineVat, int $minimalAmount): DataLayerItemFactory
    {
        $price = $this->createMock(ProductVariantPrice::class);
        $price->method('getMinimalAmount')->willReturn($minimalAmount);
        $price->method('setVatCalculationType')->willReturnSelf();
        $price->method('getPrice')->willReturnOnConsecutiveCalls($lineNoVat, $lineVat);

        $priceFactory = $this->createMock(ProductVariantPriceFactory::class);
        $priceFactory->method('create')->willReturn($price);

        return new DataLayerItemFactory($priceFactory, $this->createMock(CurrencyManager::class));
    }

    private function createVariant(): ProductVariant
    {
        $category = $this->createMock(Category::class);
        $category->method('getCategorySubCategories')->willReturn(new ArrayCollection());
        $categoryProduct = $this->createMock(CategoryProduct::class);
        $categoryProduct->method('getCategory')->willReturn($category);

        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Yoga mat');
        $product->method('getCategoryProducts')->willReturn(new ArrayCollection([$categoryProduct]));

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getId')->willReturn(7);
        $variant->method('getProduct')->willReturn($product);

        return $variant;
    }
}
