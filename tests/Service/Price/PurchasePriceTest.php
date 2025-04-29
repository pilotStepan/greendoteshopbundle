<?php

namespace Greendot\EshopBundle\Tests\Service\Price;

use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\DiscountCalculationType as DiscCalc;
use Greendot\EshopBundle\Enum\VatCalculationType as VatCalc;
use PHPUnit\Framework\TestCase;

class PurchasePriceTest extends TestCase
{
    public function testBasicTotals(
        array    $ppv,
        VatCalc  $vatCalc,
        DiscCalc $discCalc,
        Currency $currency,
        float    $expectedPurchasePrice,
    ): void
    {
        // TODO: not ready yet
    }

    public function testServicePrices(
        array          $ppv,
        VatCalc        $vatCalc,
        DiscCalc       $discCalc,
        Currency       $currency,
        float          $expectedTotals,
        Transportation $transportation,
        Payment        $payment,
        // TODO
    ): void
    {
        // TODO: not ready yet
    }
}