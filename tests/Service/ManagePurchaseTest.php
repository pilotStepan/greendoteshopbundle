<?php

namespace Greendot\EshopBundle\Tests\Service;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Bundle\SecurityBundle\Security;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Money\Money;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Parcel\TransportationAPI;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Parcel\ParcelServiceInterface;
use Greendot\EshopBundle\Parcel\Message\CreateParcelMessage;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Service\Price\PurchasePrice;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\Repository\Project\ConversionRateRepository;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\SumDiscountStrategy;
use Greendot\EshopBundle\Service\Price\Extension\DiscountCombination\HighestDiscountStrategy;

class ManagePurchaseTest extends TestCase
{
    private PurchaseRepository&MockObject $purchaseRepository;
    private MessageBusInterface&MockObject $bus;
    private ManagePurchase $managePurchase;

    protected function setUp(): void
    {
        $this->purchaseRepository = $this->createMock(PurchaseRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->managePurchase = $this->createManagePurchase(
            $this->purchaseRepository,
            $this->bus,
            new ParcelServiceProvider([]),
        );
    }

    public function testConfirmBankTransferPaymentDoesNothingWhenAlreadyPaid(): void
    {
        $purchase = new Purchase();
        $purchase->assignWorkflowFlag(PWC::F_PAYMENT_SUCCESS->value);

        $purchaseFlow = $this->createMock(WorkflowInterface::class);
        $purchaseFlow->expects($this->never())->method('apply');

        $managePurchase = $this->createManagePurchase($this->purchaseRepository, $this->bus, new ParcelServiceProvider([]), $purchaseFlow);

        $managePurchase->applyBankTransferPayment($purchase, new PaymentType());
    }

    public function testConfirmBankTransferPaymentAppliesTransitionAndSwitchesPaymentType(): void
    {
        $purchase = new Purchase();
        $paymentType = new PaymentType();

        $purchaseFlow = $this->createMock(WorkflowInterface::class);
        $purchaseFlow->expects($this->once())->method('apply')->with($purchase, PWC::T_PAY_PAY->value);

        $managePurchase = $this->createManagePurchase($this->purchaseRepository, $this->bus, new ParcelServiceProvider([]), $purchaseFlow);

        $managePurchase->applyBankTransferPayment($purchase, $paymentType);

        $this->assertSame($paymentType, $purchase->getPaymentType());
    }

    public function testConfirmBankTransferPaymentForwardsContextToWorkflow(): void
    {
        $purchase = new Purchase();
        $paymentType = new PaymentType();
        $context = ['performed_by' => 'system', 'source' => 'rb_bank', 'variableSymbol' => '123'];

        $purchaseFlow = $this->createMock(WorkflowInterface::class);
        $purchaseFlow->expects($this->once())->method('apply')->with($purchase, PWC::T_PAY_PAY->value, $context);

        $managePurchase = $this->createManagePurchase($this->purchaseRepository, $this->bus, new ParcelServiceProvider([]), $purchaseFlow);

        $managePurchase->applyBankTransferPayment($purchase, $paymentType, $context);
    }

    public function testAddProductVariantToPurchaseNewItem(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $productVariant = $this->createMock(ProductVariant::class);
        $productVariant->method('getId')->willReturn(123);

        $purchase->method('getProductVariants')
            ->willReturn(new ArrayCollection());

        $purchase->expects($this->once())
            ->method('addProductVariant')
            ->with($this->callback(function ($ppv) use ($productVariant) {
                return $ppv->getProductVariant() === $productVariant
                    && $ppv->getAmount() === 2;
            }));

        $result = $this->managePurchase->addProductVariantToPurchase($purchase, $productVariant, 2);
        $this->assertSame($purchase, $result);
    }

    public function testAddProductVariantToPurchaseExistingItem(): void
    {
        $productVariant = $this->createMock(ProductVariant::class);
        $productVariant->method('getId')->willReturn(123);

        $existingPPV = $this->createMock(PurchaseProductVariant::class);
        $existingPPV->method('getProductVariant')->willReturn($productVariant);
        $existingPPV->method('getAmount')->willReturn(2);
        $existingPPV->expects($this->once())
            ->method('setAmount')
            ->with(3);

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getProductVariants')
            ->willReturn(new ArrayCollection([$existingPPV]));

        $result = $this->managePurchase->addProductVariantToPurchase($purchase, $productVariant, 1);
        $this->assertSame($purchase, $result);
    }

    public function testCalculateInquiryNumberWithPurchase(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getDateIssue')->willReturn(new \DateTime('2023-01-01'));
        $purchase->method('getId')->willReturn(456);

        $result = $this->managePurchase->generateInquiryNumber($purchase);
        $this->assertSame('1672531200456', $result);
    }

    public function testGenerateTransportDataDispatchesMessageWhenServiceExists(): void
    {
        $transportationAPI = TransportationAPI::PACKETA;

        $transportation = new Transportation();
        $transportation->setTransportationAPI($transportationAPI);

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getId')->willReturn(42);

        $parcelService = $this->createMock(ParcelServiceInterface::class);
        $parcelService->method('supports')->with($transportationAPI)->willReturn(true);

        $managePurchase = $this->createManagePurchase(
            $this->purchaseRepository,
            $this->bus,
            new ParcelServiceProvider([$parcelService]),
        );

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CreateParcelMessage::class))
            ->willReturn(new Envelope(new CreateParcelMessage(42)));

        $managePurchase->generateTransportData($purchase);
    }

    public function testGenerateTransportDataDoesNothingWhenNoService(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getTransportation')->willReturn(null);

        $this->bus->expects($this->never())->method('dispatch');

        $this->managePurchase->generateTransportData($purchase);
    }

    public function testFindPurchaseByInquiryNumberThrowsExceptionForShortInquiryNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Inquiry ID has a wrong format.");
        $this->managePurchase->findPurchaseByInquiryNumber("0123456789");
    }

    public function testFindPurchaseByInquiryNumberThrowsExceptionWhenPurchaseNotFound(): void
    {
        $inquiryNumber = "0123456789123";
        $this->purchaseRepository->expects($this->once())
            ->method('find')
            ->with("123")
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Purchase not found for inquiry number: $inquiryNumber.");
        $this->managePurchase->findPurchaseByInquiryNumber($inquiryNumber);
    }

    public function testFindPurchaseByInquiryNumberReturnsPurchase(): void
    {
        $inquiryNumber = "0123456789123";
        $dummyPurchase = new Purchase();
        $this->purchaseRepository->expects($this->once())
            ->method('find')
            ->with("123")
            ->willReturn($dummyPurchase);

        $result = $this->managePurchase->findPurchaseByInquiryNumber($inquiryNumber);
        $this->assertSame($dummyPurchase, $result);
    }

    /**
     * Regression coverage for the plain/Money currency split: preparePrices() must price the
     * plain (float) fields in the ambient session/global currency exactly as before the Money
     * feature, and only the *Money fields in the purchase's own currency snapshot - never the
     * other way around, and never mixing the two on a single field.
     */
    public function testPreparePricesUsesAmbientCurrencyForPlainFieldsAndPurchaseCurrencyForMoney(): void
    {
        $displayCurrency = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $purchaseCurrency = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        $purchase = new Purchase();
        $purchase->setCurrency($purchaseCurrency);

        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->with(['isDefault' => true])->willReturn($displayCurrency);

        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\SessionInterface::class);
        $session->method('get')->willReturn(null);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $currencyManager = new CurrencyManager($requestStack, $currencyRepository);

        $seenCurrencies = [];
        $purchasePriceCalc = $this->createMock(PurchasePrice::class);
        $purchasePriceCalc->method('getPrice')->willReturn(100.0);
        $purchasePriceCalc->method('getTransportationPrice')->willReturn(null);
        $purchasePriceCalc->method('getPaymentPrice')->willReturn(null);
        $purchasePriceCalc->method('setCurrency')->willReturnCallback(
            function (Currency $c) use (&$seenCurrencies, $purchasePriceCalc) {
                $seenCurrencies[] = $c;
                return $purchasePriceCalc;
            }
        );
        $purchasePriceCalc->method('getMoney')->willReturn(new Money(100.0, 'EUR'));

        $purchasePriceFactory = $this->createMock(PurchasePriceFactory::class);
        $purchasePriceFactory->expects($this->once())
            ->method('create')
            ->with($purchase, $displayCurrency, VatCalculationType::WithVAT)
            ->willReturn($purchasePriceCalc);

        $productVariantPriceFactory = $this->createMock(ProductVariantPriceFactory::class);

        $managePurchase = new ManagePurchase(
            $currencyManager,
            $purchasePriceFactory,
            $productVariantPriceFactory,
            $this->purchaseRepository,
            new ManageVies($this->createMock(LoggerInterface::class)),
            $this->bus,
            new ParcelServiceProvider([]),
            $this->createMock(WorkflowInterface::class),
        );

        $managePurchase->preparePrices($purchase);

        // The calculator is built once, in the ambient currency (asserted via the `with()`
        // matcher on create() above); its currency is switched to the purchase's own snapshot
        // exactly once, right before the Money fields are read.
        $this->assertSame([$purchaseCurrency], $seenCurrencies);
        $this->assertSame(100.0, $purchase->getTotalPrice());
        $this->assertSame('EUR', $purchase->getTotalMoney()?->getIso());
    }

    /**
     * ensureCurrency() is checkout's last synchronous guard against a purchase whose currency
     * snapshot has drifted from its PaymentType's own currency (e.g. a client PATCHing `currency`
     * directly after PaymentType was set, bypassing Purchase::setPaymentType()'s sync) - it must
     * reject checkout rather than silently proceeding with mismatched data.
     */
    public function testEnsureCurrencyThrowsWhenCurrencyDoesNotMatchPaymentTypeCurrency(): void
    {
        $czk = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);

        $paymentType = (new PaymentType())->setCurrency($eur);
        $purchase = new Purchase();
        $purchase->setCurrency($czk); // bare assignment, bypassing setPaymentType()'s sync
        $this->setPaymentTypeWithoutSync($purchase, $paymentType);

        $managePurchase = $this->createManagePurchase(
            $this->purchaseRepository,
            $this->bus,
            new ParcelServiceProvider([]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/currency/i');

        $managePurchase->ensureCurrency($purchase);
    }

    public function testEnsureCurrencyDoesNothingWhenCurrencyAlreadyMatchesPaymentTypeCurrency(): void
    {
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);
        $paymentType = (new PaymentType())->setCurrency($eur);
        $purchase = new Purchase();
        $purchase->setPaymentType($paymentType); // real sync: currency becomes $eur

        $managePurchase = $this->createManagePurchase(
            $this->purchaseRepository,
            $this->bus,
            new ParcelServiceProvider([]),
        );

        $managePurchase->ensureCurrency($purchase);

        $this->assertSame($eur, $purchase->getCurrency());
    }

    public function testEnsureCurrencyFillsNullCurrencyFromPaymentTypeCurrency(): void
    {
        $eur = (new Currency())->setName('EUR')->setSymbol('€')->setRounding(2)->setIsDefault(false);
        $paymentType = (new PaymentType())->setCurrency($eur);
        $purchase = new Purchase();
        $this->setPaymentTypeWithoutSync($purchase, $paymentType);
        // Purchase starts with no currency snapshot at all.

        $managePurchase = $this->createManagePurchase(
            $this->purchaseRepository,
            $this->bus,
            new ParcelServiceProvider([]),
        );

        $managePurchase->ensureCurrency($purchase);

        $this->assertSame($eur, $purchase->getCurrency());
    }

    public function testEnsureCurrencyFillsNullCurrencyFromShopDefaultWhenPaymentTypeHasNone(): void
    {
        $default = (new Currency())->setName('CZK')->setSymbol('Kč')->setRounding(0)->setIsDefault(true);
        $paymentType = new PaymentType(); // no currency of its own
        $purchase = new Purchase();
        $this->setPaymentTypeWithoutSync($purchase, $paymentType);

        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $currencyRepository->method('findOneBy')->with(['isDefault' => true])->willReturn($default);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willThrowException(
            new \Symfony\Component\HttpFoundation\Exception\SessionNotFoundException(),
        );
        $currencyManager = new CurrencyManager($requestStack, $currencyRepository);

        $managePurchase = new ManagePurchase(
            $currencyManager,
            $this->createMock(PurchasePriceFactory::class),
            $this->createMock(ProductVariantPriceFactory::class),
            $this->purchaseRepository,
            new ManageVies($this->createMock(LoggerInterface::class)),
            $this->bus,
            new ParcelServiceProvider([]),
            $this->createMock(WorkflowInterface::class),
        );

        $managePurchase->ensureCurrency($purchase);

        $this->assertSame($default, $purchase->getCurrency());
    }

    /**
     * Sets PaymentType on a Purchase without going through Purchase::setPaymentType()'s
     * pre-checkout currency sync, so tests can construct a purchase/paymentType pairing with
     * an independently-controlled currency snapshot.
     */
    private function setPaymentTypeWithoutSync(Purchase $purchase, PaymentType $paymentType): void
    {
        $property = new \ReflectionProperty(Purchase::class, 'PaymentType');
        $property->setAccessible(true);
        $property->setValue($purchase, $paymentType);
    }

    private function createManagePurchase(
        PurchaseRepository $purchaseRepository,
        MessageBusInterface $bus,
        ParcelServiceProvider $parcelServiceProvider,
        ?WorkflowInterface $purchaseFlow = null,
    ): ManagePurchase {
        $conversionRateRepository = $this->createMock(ConversionRateRepository::class);
        $priceRepository = $this->createMock(PriceRepository::class);
        $settingsRepository = $this->createMock(SettingsRepository::class);
        $handlingPriceRepository = $this->createMock(HandlingPriceRepository::class);
        $productProductRepository = $this->createMock(ProductProductRepository::class);
        $security = $this->createMock(Security::class);
        $discountService = $this->createMock(DiscountService::class);
        $requestStack = $this->createMock(RequestStack::class);
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $logger = $this->createMock(LoggerInterface::class);

        $priceUtils = new PriceUtils($conversionRateRepository);
        $serviceCalculationUtils = new ServiceCalculationUtils($handlingPriceRepository, $priceUtils);
        $discountLocator = new \Symfony\Component\DependencyInjection\ServiceLocator([
            'sum'     => fn() => new SumDiscountStrategy(),
            'highest' => fn() => new HighestDiscountStrategy(),
        ]);

        $productVariantPriceFactory = new ProductVariantPriceFactory(
            $security,
            $priceRepository,
            $discountService,
            $priceUtils,
            $settingsRepository,
            $productProductRepository,
            $discountLocator,
            'sum',
        );
        $purchasePriceFactory = new PurchasePriceFactory(
            $productVariantPriceFactory,
            $priceUtils,
            $serviceCalculationUtils,
            $settingsRepository,
        );
        $currencyManager = new CurrencyManager($requestStack, $currencyRepository);
        $manageVies = new ManageVies($logger);

        return new ManagePurchase(
            $currencyManager,
            $purchasePriceFactory,
            $productVariantPriceFactory,
            $purchaseRepository,
            $manageVies,
            $bus,
            $parcelServiceProvider,
            $purchaseFlow ?? $this->createMock(WorkflowInterface::class),
        );
    }
}
