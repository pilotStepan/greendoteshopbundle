<?php

namespace Greendot\EshopBundle\Tests\EventSubscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Consent;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\EventSubscriber\PurchaseStateSubscriber;
use Greendot\EshopBundle\Repository\Project\ConsentRepository;
use Greendot\EshopBundle\Repository\Project\ConversionRateRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\DateService;
use Greendot\EshopBundle\Service\DiscountService;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Service\Price\ServiceCalculationUtils;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;


class PurchaseStateSubscriberTest extends TestCase
{
    private MockObject $entityManager;
    private MockObject $manageVoucher;
    private ManageClientDiscount $manageClientDiscount;
    private MockObject $dateService;
    private MockObject $eventDispatcher;
    private MockObject $purchaseWorkflow;
    private PurchaseStateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manageVoucher = $this->createMock(ManageVoucher::class);
        $this->manageClientDiscount = new ManageClientDiscount($this->createMock(EntityManagerInterface::class));
        $this->dateService = $this->createMock(DateService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->purchaseWorkflow = $this->createMock(WorkflowInterface::class);

        $this->subscriber = new PurchaseStateSubscriber(
            $this->entityManager,
            $this->manageVoucher,
            $this->buildManagePurchase(),
            $this->manageClientDiscount,
            $this->dateService,
            $this->eventDispatcher,
            $this->purchaseWorkflow,
        );
    }

    public function testGuardReceiveBlocksWhenNoProductVariants(): void
    {
        $purchase = $this->createPurchaseMock(productVariants: []);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nelze vytvořit prázdnou objednávku');
    }

    public function testGuardReceiveBlocksWhenNoClient(): void
    {
        $purchase = $this->createPurchaseMock(client: null);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Objednávka musí mít přiřazeného klienta');
    }

    public function testGuardReceiveBlocksWhenNoPaymentType(): void
    {
        $purchase = $this->createPurchaseMock(client: new Client(), paymentType: null);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nebyl vybrán typ platby');
    }

    public function testGuardReceiveBlocksWhenNoTransportation(): void
    {
        $paymentType = $this->createValidPaymentType();
        $purchase = $this->createPurchaseMock(paymentType: $paymentType, transportation: null);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nebyla vybrána doprava');
    }

    public function testGuardReceiveBlocksWhenIncompatiblePaymentAndTransport(): void
    {
        $paymentType = $this->createMock(PaymentType::class);
        $transportation = $this->createMock(Transportation::class);

        $paymentType->method('getTransportations')
            ->willReturn(new ArrayCollection([$this->createMock(Transportation::class)]));

        $purchase = $this->createPurchaseMock(client: new Client(), paymentType: $paymentType, transportation: $transportation);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nekompatibilní typ platby a dopravy');
    }

    public function testGuardReceiveBlocksWhenMissingConsent(): void
    {
        $transportation = $this->createValidTransportation();
        $paymentType = $this->createValidPaymentType($transportation);

        $missingConsent = (new Consent())->setDescription('GDPR Consent');

        $consentRepo = $this->createMock(ConsentRepository::class);
        $consentRepo->method('findMissingRequiredConsent')->willReturn($missingConsent);

        $this->entityManager->method('getRepository')->with(Consent::class)->willReturn($consentRepo);

        $purchase = $this->createPurchaseMock(client: new Client(), paymentType: $paymentType, transportation: $transportation, consents: []);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Povinný souhlas nebyl zaškrtnut: GDPR Consent');
    }

    public function testGuardReceiveBlocksWhenInvalidVoucher(): void
    {
        $transportation = $this->createValidTransportation();
        $paymentType = $this->createValidPaymentType($transportation);

        $this->manageVoucher->method('validateVouchersTransition')
            ->willThrowException(new LogicException('Nelze uplatnit neplatný voucher: INVALID123'));

        $purchase = $this->createPurchaseMock(paymentType: $paymentType, transportation: $transportation);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Nelze uplatnit neplatný voucher: INVALID123');
    }

    public function testGuardReceiveBlocksWhenInvalidClientDiscount(): void
    {
        $transportation = $this->createValidTransportation();
        $paymentType = $this->createValidPaymentType($transportation);

        // A used discount will fail guardUse validation
        $discount = new ClientDiscount();
        $discount->setIsUsed(true);

        $purchase = $this->createPurchaseMock(paymentType: $paymentType, transportation: $transportation, clientDiscount: $discount);

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertBlockedWithMessage($event, 'Slevový kupón není platný');
    }

    public function testGuardReceiveAllowsTransitionWhenAllValid(): void
    {
        $paymentType = $this->createMock(PaymentType::class);
        $transportation = $this->createMock(Transportation::class);
        $paymentType->method('getTransportations')->willReturn(new ArrayCollection([$transportation]));

        $consentRepo = $this->createMock(ConsentRepository::class);
        $consentRepo->method('findMissingRequiredConsent')->willReturn(null);
        $this->entityManager->method('getRepository')->with(Consent::class)->willReturn($consentRepo);

        $this->manageVoucher->method('validateVouchersTransition')->willReturn(true);

        $purchase = $this->createPurchaseMock(
            client: new Client(),
            paymentType: $paymentType,
            transportation: $transportation,
            consents: [new Consent()],
        );

        $event = $this->createGuardEvent($purchase);
        $this->subscriber->onGuardReceive($event);

        $this->assertFalse($event->isBlocked(), 'Transition should not be blocked');
    }

    private function buildManagePurchase(): ManagePurchase
    {
        $conversionRateRepo = $this->createMock(ConversionRateRepository::class);
        $priceRepository = $this->createMock(PriceRepository::class);
        $settingsRepository = $this->createMock(SettingsRepository::class);
        $handlingPriceRepo = $this->createMock(HandlingPriceRepository::class);
        $productProductRepo = $this->createMock(ProductProductRepository::class);
        $security = $this->createMock(Security::class);
        $discountService = $this->createMock(DiscountService::class);
        $requestStack = $this->createMock(RequestStack::class);
        $currencyRepository = $this->createMock(CurrencyRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);

        $priceUtils = new PriceUtils($conversionRateRepo);
        $serviceCalculationUtils = new ServiceCalculationUtils($handlingPriceRepo, $priceUtils);
        $pvPriceFactory = new ProductVariantPriceFactory(
            $security,
            $priceRepository,
            $discountService,
            $priceUtils,
            $settingsRepository,
            $productProductRepo,
        );
        $purchasePriceFactory = new PurchasePriceFactory(
            $pvPriceFactory,
            $priceUtils,
            $serviceCalculationUtils,
            $settingsRepository,
        );

        return new ManagePurchase(
            new CurrencyManager($requestStack, $currencyRepository),
            $purchasePriceFactory,
            $pvPriceFactory,
            $this->createMock(PurchaseRepository::class),
            new ManageVies($logger),
            $bus,
            new ParcelServiceProvider([]),
        );
    }

    private function createPurchaseProductVariantMock(): MockObject
    {
        $productVariant = $this->createMock(\Greendot\EshopBundle\Entity\Project\ProductVariant::class);
        $productVariant->method('getAvailability')->willReturn(null);

        $ppv = $this->createMock(\Greendot\EshopBundle\Entity\Project\PurchaseProductVariant::class);
        $ppv->method('getProductVariant')->willReturn($productVariant);

        return $ppv;
    }

    private function createPurchaseMock(
        ?array          $productVariants = null,
        ?Client         $client = new Client(),
        ?MockObject     $paymentType = null,
        ?MockObject     $transportation = null,
        array           $consents = [new Consent()],
        ?ClientDiscount $clientDiscount = null
    ): MockObject
    {
        if ($productVariants === null) {
            $productVariants = [$this->createPurchaseProductVariantMock()];
        }

        if ($paymentType && $transportation) {
            $paymentType->method('getTransportations')->willReturn(new ArrayCollection([$transportation]));
        }

        $purchaseAddress = $this->createMock(PurchaseAddress::class);
        $purchaseAddress->method('getDic')->willReturn(null);

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getProductVariants')->willReturn(new ArrayCollection($productVariants));
        $purchase->method('getClient')->willReturn($client);
        $purchase->method('getPaymentType')->willReturn($paymentType);
        $purchase->method('getTransportation')->willReturn($transportation);
        $purchase->method('getConsents')->willReturn(new ArrayCollection($consents));
        $purchase->method('getClientDiscount')->willReturn($clientDiscount);
        $purchase->method('getVouchersUsed')->willReturn(new ArrayCollection());
        $purchase->method('getPurchaseAddress')->willReturn($purchaseAddress);
        return $purchase;
    }

    private function createValidPaymentType(MockObject $transportation = null): MockObject
    {
        $paymentType = $this->createMock(PaymentType::class);
        $paymentType->method('getTransportations')
            ->willReturn(new ArrayCollection($transportation ? [$transportation] : []));
        return $paymentType;
    }

    private function createValidTransportation(): MockObject
    {
        return $this->createMock(Transportation::class);
    }

    private function createGuardEvent(object $subject): GuardEvent
    {
        return new GuardEvent(
            $subject,
            $this->createMock(Marking::class),
            $this->createMock(Transition::class),
            $this->createMock(Workflow::class)
        );
    }

    private function assertBlockedWithMessage(GuardEvent $event, string $expectedMessage): void
    {
        $this->assertTrue($event->isBlocked(), 'Transition should be blocked');
        $blockers = iterator_to_array($event->getTransitionBlockerList());
        $this->assertStringContainsString($expectedMessage, $blockers[0]->getMessage());
    }
}
