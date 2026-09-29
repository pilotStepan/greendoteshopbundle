<?php

namespace Greendot\EshopBundle\Tests\Invoice;

use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Enum\TransportationAction;
use Greendot\EshopBundle\Invoice\Factory\InvoiceDataFactory;
use Greendot\EshopBundle\Repository\Project\CountryRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\QRcodeGenerator;

/**
 * The legacy CZK/EUR fields (currencyPrimary/currencySecondary, *Czk/*Eur, ...) always mean the
 * shop's configured default + secondary currency, regardless of the purchase's own currency -
 * unchanged from before the Money feature. Only toPayVatMoney/toPayNoVatMoney follow the
 * purchase's own currency snapshot.
 */
class InvoiceDataFactoryTest extends TestCase
{
    public function testLegacyFieldsAlwaysUseShopDefaultAndSecondaryCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        $purchase = $this->buildPurchase($eur);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $eur);

        $invoiceData = $factory->create($purchase);

        $this->assertSame('CZK', $invoiceData->currencyPrimary->getIso());
        $this->assertSame('EUR', $invoiceData->currencySecondary->getIso());
    }

    public function testToPayMoneyFollowsThePurchasesOwnCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        $purchase = $this->buildPurchase($eur);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $eur);

        $invoiceData = $factory->create($purchase);

        $this->assertSame('EUR', $invoiceData->toPayVatMoney->iso);
        $this->assertSame('EUR', $invoiceData->toPayNoVatMoney->iso);
    }

    /**
     * Additive twin of testToPayMoneyFollowsThePurchasesOwnCurrency(): the secondary Money
     * fields pair with the primary purchase-currency ones (never both CZK/EUR), without
     * touching the legacy currencyPrimary/currencySecondary fields tested above.
     */
    public function testToPayMoneySecondaryIsTheCorrectCounterpart(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        // Purchase placed in EUR (not the default) -> secondary must be the default, CZK.
        $purchase = $this->buildPurchase($eur);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $eur);

        $invoiceData = $factory->create($purchase);

        $this->assertSame('EUR', $invoiceData->toPayVatMoney->iso);
        $this->assertSame('CZK', $invoiceData->toPayVatMoneySecondary->iso);
        $this->assertSame('CZK', $invoiceData->toPayNoVatMoneySecondary->iso);
    }

    public function testToPayMoneySecondaryIsEurWhenPrimaryIsTheDefault(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        // Purchase placed in the default currency (CZK) -> secondary must be EUR.
        $purchase = $this->buildPurchase($czk);
        $factory = $this->makeFactory($czk, $eur, currentPurchaseCurrency: $czk);

        $invoiceData = $factory->create($purchase);

        $this->assertSame('CZK', $invoiceData->toPayVatMoney->iso);
        $this->assertSame('EUR', $invoiceData->toPayVatMoneySecondary->iso);
        $this->assertSame('EUR', $invoiceData->toPayNoVatMoneySecondary->iso);
    }

    private function buildPurchase(Currency $currency): Purchase
    {
        $client = (new Client())->setName('John')->setSurname('Doe')->setIsAnonymous(true);
        $address = (new PurchaseAddress())->setStreet('Testovací 123')->setCity('Praha')->setZip('10000')->setCountry('CZ');
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
            ->setDateIssue(new \DateTime())
        ;
        (new \ReflectionProperty($purchase, 'id'))->setValue($purchase, 1);

        return $purchase;
    }

    private function makeFactory(Currency $czk, Currency $eur, Currency $currentPurchaseCurrency): InvoiceDataFactory
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

        $activeIso = $currentPurchaseCurrency->getIso();

        $calculator = $this->createMock(PurchasePrice::class);
        $calculator->method('setVatCalculationType')->willReturnSelf();
        $calculator->method('setDiscountCalculationType')->willReturnSelf();
        $calculator->method('setVoucherCalculationType')->willReturnSelf();
        $calculator->method('setCurrency')->willReturnCallback(function (Currency $c) use ($calculator, &$activeIso) {
            $activeIso = $c->getIso();
            return $calculator;
        });
        $calculator->method('getPrice')->willReturn(100.0);
        $calculator->method('getMoney')->willReturnCallback(function () use (&$activeIso) {
            return new \Greendot\EshopBundle\Money\Money(100.0, $activeIso);
        });
        $calculator->method('getTransportationPrice')->willReturn(100.0);
        $calculator->method('getTransportationMoney')->willReturnCallback(function () use (&$activeIso) {
            return new \Greendot\EshopBundle\Money\Money(100.0, $activeIso);
        });
        $calculator->method('getPaymentPrice')->willReturn(100.0);
        $calculator->method('getPaymentMoney')->willReturnCallback(function () use (&$activeIso) {
            return new \Greendot\EshopBundle\Money\Money(100.0, $activeIso);
        });
        $calculator->method('getDiscountPercentage')->willReturn(0.0);
        $calculator->method('getDiscountValue')->willReturn(0.0);
        $calculator->method('getDiscountMoney')->willReturnCallback(function () use (&$activeIso) {
            return new \Greendot\EshopBundle\Money\Money(0.0, $activeIso);
        });
        $calculator->method('getVouchersUsedValue')->willReturn(0.0);
        $calculator->method('getVouchersUsedMoney')->willReturnCallback(function () use (&$activeIso) {
            return new \Greendot\EshopBundle\Money\Money(0.0, $activeIso);
        });
        $calculator->method('getVouchersUsed')->willReturn([]);

        $purchasePriceFactory = $this->createMock(PurchasePriceFactory::class);
        $purchasePriceFactory->method('create')->willReturnCallback(
            function (Purchase $purchase, Currency $currency) use ($calculator, &$activeIso) {
                $activeIso = $currency->getIso();
                return $calculator;
            }
        );

        return new InvoiceDataFactory(
            $purchasePriceFactory,
            $this->createMock(ProductVariantPriceFactory::class),
            $currencyRepository,
            $currencyManager,
            $this->createMock(CountryRepository::class),
            $this->createMock(ParameterRepository::class),
            $this->createMock(QRcodeGenerator::class),
            'EUR',
        );
    }
}
