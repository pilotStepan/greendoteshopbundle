<?php

namespace Greendot\EshopBundle\Tests\Service\Price\Extension\DiscountCombination;

use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\HighestDiscountStrategy;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\SumDiscountStrategy;
use PHPUnit\Framework\TestCase;

class DiscountCombinationStrategyTest extends TestCase
{
    public function testSumAddsProductAndClientDiscount(): void
    {
        $strategy = new SumDiscountStrategy();
        $this->assertEqualsWithDelta(15.0, $strategy->combine(10.0, 5.0), 0.001);
    }

    public function testSumTreatsNullClientDiscountAsZero(): void
    {
        $strategy = new SumDiscountStrategy();
        $this->assertEqualsWithDelta(10.0, $strategy->combine(10.0, null), 0.001);
    }

    public function testHighestReturnsProductDiscountWhenItIsLarger(): void
    {
        $strategy = new HighestDiscountStrategy();
        $this->assertEqualsWithDelta(15.0, $strategy->combine(15.0, 5.0), 0.001);
    }

    public function testHighestReturnsClientDiscountWhenItIsLarger(): void
    {
        $strategy = new HighestDiscountStrategy();
        $this->assertEqualsWithDelta(15.0, $strategy->combine(10.0, 15.0), 0.001);
    }

    public function testHighestTreatsNullClientDiscountAsZero(): void
    {
        $strategy = new HighestDiscountStrategy();
        $this->assertEqualsWithDelta(10.0, $strategy->combine(10.0, null), 0.001);
    }

    public function testHighestWithEqualDiscountsReturnsThatValue(): void
    {
        $strategy = new HighestDiscountStrategy();
        $this->assertEqualsWithDelta(10.0, $strategy->combine(10.0, 10.0), 0.001);
    }
}