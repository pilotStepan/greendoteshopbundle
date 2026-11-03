<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Tests\Service\Price\PriceCalculationFactoryUtil as FactoryUtil;

/**
 * Prices resolve purely by Purchase::getPricingDate(): a not yet issued purchase (cart, wishlist, draft, ...)
 * is priced now no matter how old it is, an issued one keeps the prices of its issue date.
 */
class PricingDateTest extends PriceCalculationTestCase
{
    public function testOldUnissuedCartIsPricedNow(): void
    {
        $cart = new Purchase();
        (new \ReflectionProperty(Purchase::class, 'date_created'))->setValue($cart, new \DateTimeImmutable('-30 days'));
        $before = new \DateTime();

        $date = $this->priceLookupDateFor($cart);

        $this->assertGreaterThanOrEqual($before, $date);
    }

    public function testIssuedPurchaseIsPricedAtItsIssueDate(): void
    {
        $issuedAt = new \DateTime('2026-01-15 10:00:00');
        $order = new Purchase();
        (new \ReflectionProperty(Purchase::class, 'date_issue'))->setValue($order, $issuedAt);

        $this->assertEquals($issuedAt, $this->priceLookupDateFor($order));
    }

    public function testConversionRateOfUnissuedPurchaseIsResolvedNow(): void
    {
        $cart = new Purchase();
        $cart->setMarking(['cart' => 1]);
        $before = new \DateTime();

        $this->assertGreaterThanOrEqual($before, $this->conversionRateDateFor($cart));
    }

    public function testConversionRateOfIssuedPurchaseIsResolvedAtIssueDate(): void
    {
        $issuedAt = new \DateTime('2026-01-15 10:00:00');
        $order = new Purchase();
        (new \ReflectionProperty(Purchase::class, 'date_issue'))->setValue($order, $issuedAt);

        $this->assertEquals($issuedAt, $this->conversionRateDateFor($order));
    }

    private function priceLookupDateFor(Purchase $purchase): \DateTimeInterface
    {
        $productVariant = $this->createMock(ProductVariant::class);
        /** @var PurchaseProductVariant&MockObject $ppv */
        $ppv = $this->createMock(PurchaseProductVariant::class);
        $ppv->method('getProductVariant')->willReturn($productVariant);
        $ppv->method('getAmount')->willReturn(1);
        $ppv->method('getPurchase')->willReturn($purchase);
        $this->security->method('getUser')->willReturn(null);

        $lookupDate = null;
        $this->priceRepository->expects($this->once())
            ->method('findPricesByDateAndProductVariantNew')
            ->willReturnCallback(function ($variant, \DateTimeInterface $date) use ($productVariant, &$lookupDate) {
                $this->assertSame($productVariant, $variant);
                $lookupDate = $date;

                return [['price' => FactoryUtil::makePrice(100, 21)]];
            });

        $this->createProductVariantPrice($ppv, FactoryUtil::czk(), VatCalc::WithVAT, DiscCalc::WithDiscount, null)->getPrice();

        return $lookupDate;
    }

    private function conversionRateDateFor(Purchase $purchase): \DateTimeInterface
    {
        $lookupDate = null;
        $repository = $this->createMock(\Greendot\EshopBundle\Repository\Project\ConversionRateRepository::class);
        $repository->method('getByDate')->willReturnCallback(function (Currency $c, \DateTime $date) use (&$lookupDate) {
            $lookupDate = $date;
            return null;
        });

        (new \Greendot\EshopBundle\Service\Price\PriceUtils($repository))->getConversionRate(FactoryUtil::czk(), $purchase);

        return $lookupDate;
    }
}
