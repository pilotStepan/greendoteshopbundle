<?php

namespace Greendot\EshopBundle\Tests\Service;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Bundle\SecurityBundle\Security;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\Vies\ManageVies;
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
