<?php

namespace Greendot\EshopBundle\Tests\Mail;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Enum\TransportationAction;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;

/**
 * primaryCurrency and every *Czk/*Eur field always mean the shop's configured default +
 * secondary currency, regardless of the purchase's own currency - unchanged from before the
 * Money feature. Only totalMoney follows the purchase's own currency snapshot.
 */
class OrderDataFactoryTest extends TestCase
{
    public function testLegacyFieldsAlwaysUseShopDefaultCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        $purchase = $this->buildPurchase($eur);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $eur);

        $orderData = $factory->create($purchase);

        $this->assertSame('czk', $orderData->primaryCurrency);
    }

    public function testTotalMoneyFollowsThePurchasesOwnCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        $purchase = $this->buildPurchase($eur);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $eur);

        $orderData = $factory->create($purchase);

        $this->assertSame('EUR', $orderData->totalMoney?->iso);
    }

    /**
     * Additive twin of testTotalMoneyFollowsThePurchasesOwnCurrency(): the secondary Money
     * field pairs with totalMoney (never both CZK/EUR), without touching the legacy
     * primaryCurrency/*Czk/*Eur fields tested above.
     */
    public function testTotalMoneySecondaryIsTheCorrectCounterpart(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        // Purchase placed in EUR (not the default) -> secondary must be the default, CZK.
        $purchase = $this->buildPurchase($eur);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $eur);

        $orderData = $factory->create($purchase);

        $this->assertSame('EUR', $orderData->totalMoney?->iso);
        $this->assertSame('CZK', $orderData->totalMoneySecondary?->iso);
    }

    public function testTotalMoneySecondaryIsEurWhenPrimaryIsTheDefault(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        // Purchase placed in the default currency (CZK) -> secondary must be EUR.
        $purchase = $this->buildPurchase($czk);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $czk);

        $orderData = $factory->create($purchase);

        $this->assertSame('CZK', $orderData->totalMoney?->iso);
        $this->assertSame('EUR', $orderData->totalMoneySecondary?->iso);
    }

    private function buildPurchase(Currency $currency): Purchase
    {
        $client = (new Client())->setName('John')->setSurname('Doe')->setIsAnonymous(true);
        $address = (new PurchaseAddress())->setStreet('Testovací 123')->setCity('Praha')->setZip('10000');
        $paymentType = (new PaymentType())->setCountry('CZ')->setName('Card payment')->setDescription('')->setDescritionMail('');
        $paymentType->setActionGroup(PaymentTypeActionGroup::CARD_PAYMENT); // void return, can't chain
        $transportation = (new Transportation())->setCountry('CZ')->setName('Courier')->setDescription('')->setDescriptionMail('');
        $transportation->setTransportationAction(TransportationAction::DELIVERY);

        $purchase = (new Purchase())
            ->setClient($client)
            ->setPurchaseAddress($address)
            ->setPaymentType($paymentType)
            ->setTransportation($transportation)
            ->setCurrency($currency)
        ;
        (new \ReflectionProperty($purchase, 'id'))->setValue($purchase, 1);

        return $purchase;
    }

    private function makeFactory(Currency $czk, Currency $eur, Currency $currentPurchaseCurrency): OrderDataFactory
    {
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->willReturnCallback(
            static fn(array $criteria) => match (true) {
                ($criteria['isDefault'] ?? null) === true => $czk,
                ($criteria['name'] ?? null) === 'EUR' => $eur,
                default => null,
            }
        );

        $currencyManager = $this->createMock(CurrencyManager::class);
        $currencyManager->method('getForPurchase')->willReturn($currentPurchaseCurrency);
        $currencyManager->method('getSecondaryFor')->willReturnCallback(
            static fn(Currency $primary) => $primary->isIsDefault() ? $eur : $czk
        );

        $calculator = $this->createMock(PurchasePrice::class);
        $activeIso = $czk->getIso();
        $calculator->method('setCurrency')->willReturnCallback(function (Currency $c) use ($calculator, &$activeIso) {
            $activeIso = $c->getIso();
            return $calculator;
        });
        $calculator->method('getMoney')->willReturnCallback(function () use (&$activeIso) {
            return new Money(100.0, $activeIso);
        });
        $calculator->method('getPrice')->willReturn(100.0);

        $purchasePriceFactory = $this->createMock(PurchasePriceFactory::class);
        $purchasePriceFactory->method('create')->willReturnCallback(
            function (Purchase $purchase, Currency $currency) use ($calculator, &$activeIso) {
                $activeIso = $currency->getIso();
                return $calculator;
            }
        );

        return new OrderDataFactory(
            $purchasePriceFactory,
            $this->createMock(ProductVariantPriceFactory::class),
            $currencyRepository,
            $currencyManager,
            $this->createMock(QRcodeGenerator::class),
            new StubPurchaseUrlGenerator(),
            $this->createMock(LoggerInterface::class),
            'EUR',
        );
    }
}

// PurchaseUrlGenerator is `readonly`, so it can't be doubled with createMock().
readonly class StubPurchaseUrlGenerator extends PurchaseUrlGenerator
{
    public function __construct()
    {
    }

    public function buildOrderDetailUrl(Purchase $purchase, bool $isCreated = false): string
    {
        return 'https://example.test/order/1';
    }

    public function buildTrackingUrl(Purchase $purchase): ?string
    {
        return null;
    }

    public function buildPayUrl(Purchase $purchase): string
    {
        return 'https://example.test/pay/1';
    }
}
