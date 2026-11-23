<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\EventSubscriber\TransportationEventListener;

/**
 * The cart banner ("Pro dopravu zdarma zbývá nakoupit za …") must use the same basis as the
 * actual transportation price (PurchasePrice::loadServicePrices()): the subtotal before vouchers.
 */
class TransportationEventListenerTest extends TestCase
{
    public function testAmountUntilFreeIgnoresVouchers(): void
    {
        $currency = (new Currency())->setName('CZK');
        $cart = new Purchase();
        $transportation = new Transportation();

        // 1790 Kč of products; with a 5000 Kč voucher the voucher-applied subtotal would be 0
        $priceCalc = $this->createMock(PurchasePrice::class);
        $priceCalc->method('getPrice')->willReturn(1790.0);
        $priceCalc->method('getTransportationPrice')->willReturn(0.0);

        $purchasePriceFactory = $this->createMock(PurchasePriceFactory::class);
        $purchasePriceFactory->expects($this->once())
            ->method('create')
            ->with(
                $cart,
                $currency,
                VatCalculationType::WithVAT,
                DiscountCalculationType::WithDiscount,
                VoucherCalculationType::WithoutVoucher,
            )
            ->willReturn($priceCalc);

        $listener = new TransportationEventListener(
            $this->createMock(EntityManagerInterface::class),
            $purchasePriceFactory,
            // readonly - can't be doubled; not used by the method under test
            new ServiceCalculationUtils(
                $this->createMock(HandlingPriceRepository::class),
                $this->createMock(PriceUtils::class),
            ),
            $this->createMock(CurrencyManager::class),
        );

        [$cartPrice, $amountUntilFree] = (new \ReflectionMethod($listener, 'resolveCartPriceAndThreshold'))
            ->invoke($listener, $cart, $transportation, $currency, 79.0, 1500.0);

        $this->assertSame(0.0, $cartPrice);
        $this->assertSame(0.0, $amountUntilFree);
    }
}
