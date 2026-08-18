<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\ConversionRate;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Repository\Project\ConversionRateRepository;
use Greendot\EshopBundle\Service\Price\Exception\MissingConversionRateException;

class PriceUtilsConversionRateTest extends TestCase
{
    private MockObject $conversionRateRepository;
    private PriceUtils $priceUtils;

    protected function setUp(): void
    {
        $this->conversionRateRepository = $this->createMock(ConversionRateRepository::class);
        $this->priceUtils = new PriceUtils($this->conversionRateRepository);
    }

    public function testDefaultCurrencyWithNoRateRowSynthesizesRateOne(): void
    {
        $currency = (new Currency())->setIsDefault(true);
        $this->conversionRateRepository->method('getByDate')->willReturn(null);

        $conversionRate = $this->priceUtils->getConversionRate($currency);

        $this->assertSame(1.0, $conversionRate->getRate());
        $this->assertSame($currency, $conversionRate->getCurrency());
        $this->assertInstanceOf(\DateTimeInterface::class, $conversionRate->getValidFrom());
    }

    public function testNonDefaultCurrencyWithNoRateRowThrows(): void
    {
        $currency = (new Currency())->setIsDefault(false)->setName('EUR');
        $this->conversionRateRepository->method('getByDate')->willReturn(null);

        try {
            $this->priceUtils->getConversionRate($currency);
            $this->fail('Expected MissingConversionRateException');
        } catch (MissingConversionRateException $e) {
            $this->assertStringContainsString('EUR', $e->getMessage());
        }
    }

    /**
     * Currency::$isDefault is ?bool; a not-yet-persisted Currency (isDefault
     * still null) must be treated the same as false - it must not silently
     * fall back to rate 1 just because the flag was never explicitly set.
     */
    public function testCurrencyWithUnsetIsDefaultAndNoRateRowThrows(): void
    {
        $currency = new Currency(); // isDefault left null
        $this->conversionRateRepository->method('getByDate')->willReturn(null);

        $this->expectException(MissingConversionRateException::class);

        $this->priceUtils->getConversionRate($currency);
    }

    public function testExistingConversionRateRowIsReturnedDirectlyEvenForNonDefaultCurrency(): void
    {
        $currency = (new Currency())->setIsDefault(false);
        $existingRate = new ConversionRate();
        $existingRate->setCurrency($currency);
        $existingRate->setRate(25.5);

        $this->conversionRateRepository->method('getByDate')->willReturn($existingRate);

        $result = $this->priceUtils->getConversionRate($currency);

        $this->assertSame($existingRate, $result);
    }

    public function testCartPhasePurchaseResolvesRateAsOfNowNotDateIssue(): void
    {
        $currency = (new Currency())->setIsDefault(true);
        $purchase = new Purchase();
        $purchase->setMarking([PWC::S_CART->value => 1]);
        // A stale date_issue must never be used while the purchase is still a cart.
        $purchase->setDateIssue(new \DateTime('2000-01-01'));

        $seenDate = null;
        $this->conversionRateRepository->method('getByDate')
            ->willReturnCallback(function ($currencyArg, $date) use (&$seenDate) {
                $seenDate = $date;
                return null;
            });

        $this->priceUtils->getConversionRate($currency, $purchase);

        $this->assertNotNull($seenDate);
        $this->assertGreaterThan(new \DateTime('2020-01-01'), $seenDate);
    }

    public function testFinalizedPurchaseResolvesRateAsOfItsOwnDateIssue(): void
    {
        $currency = (new Currency())->setIsDefault(true);
        $purchase = new Purchase();
        $purchase->setMarking([PWC::S_COMPLETED->value => 1]);
        $issueDate = new \DateTime('2021-06-15');
        $purchase->setDateIssue($issueDate);

        $seenDate = null;
        $this->conversionRateRepository->method('getByDate')
            ->willReturnCallback(function ($currencyArg, $date) use (&$seenDate) {
                $seenDate = $date;
                return null;
            });

        $this->priceUtils->getConversionRate($currency, $purchase);

        $this->assertEquals($issueDate, $seenDate);
    }

    public function testConvertCurrencyRoundsToCurrencyRounding(): void
    {
        $currency = (new Currency())->setRounding(2);
        $conversionRate = new ConversionRate();
        $conversionRate->setCurrency($currency);
        $conversionRate->setRate(24.315);

        $this->assertSame(round(10 * 24.315, 2), $this->priceUtils->convertCurrency(10, $conversionRate));
    }

    public function testConvertCurrencyWithNullPriceReturnsZeroWithoutTouchingRate(): void
    {
        $currency = (new Currency())->setRounding(2);
        $conversionRate = new ConversionRate();
        $conversionRate->setCurrency($currency);
        $conversionRate->setRate(24.315);

        $this->assertSame(0.0, $this->priceUtils->convertCurrency(null, $conversionRate));
    }
}
