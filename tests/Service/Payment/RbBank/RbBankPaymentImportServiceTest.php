<?php

namespace Greendot\EshopBundle\Tests\Service\Payment\RbBank;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpClient\MockHttpClient;
use Greendot\EshopBundle\Service\ManagePurchase;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Parcel\ParcelServiceProvider;
use Symfony\Component\HttpClient\Response\MockResponse;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;
use Greendot\EshopBundle\Payment\RbBank\RbBankPaymentImportService;

class RbBankPaymentImportServiceTest extends TestCase
{
    // col: validFrom;validTo;prescribed;currency;transferred;transferDate;debitAcc;debitBank;creditAcc;creditBank;VS;KS;note;status;txId
    private const ROW = '01.06.2026;30.06.2026;100.00;CZK;%s;%s;111111;0100;222222;5500;%s;0;poznamka;%d;%s';

    private PurchaseRepository&MockObject $purchaseRepository;
    private PaymentTypeRepository&MockObject $paymentTypeRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private WorkflowInterface&MockObject $purchaseFlow;
    private PaymentType $bankTransferPaymentType;

    protected function setUp(): void
    {
        $this->purchaseRepository = $this->createMock(PurchaseRepository::class);
        $this->paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->purchaseFlow = $this->createMock(WorkflowInterface::class);

        $this->bankTransferPaymentType = new PaymentType();
        $this->paymentTypeRepository
            ->method('findOneBy')
            ->with([
                'action_group' => PaymentTypeActionGroup::BANK_TRANSFER,
                'account' => '123',
                'bank_number' => '5500',
            ])
            ->willReturn($this->bankTransferPaymentType)
        ;
    }

    public function testCompletedPaymentIsMatchedAndConfirmed(): void
    {
        $purchase = new Purchase();
        $this->purchaseRepository->expects($this->once())->method('find')->with('42')->willReturn($purchase);

        $this->purchaseFlow->expects($this->once())
            ->method('apply')
            ->with($purchase, PWC::T_PAY_PAY->value, $this->callback(function (array $context) {
                $this->assertSame('system', $context['performed_by']);
                $this->assertSame('rb_bank', $context['source']);
                $this->assertSame('42', $context['variableSymbol']);
                $this->assertSame('tx-1', $context['transactionId']);
                return true;
            }))
        ;

        $this->entityManager->expects($this->once())->method('persist'); // Purchase only; state row now comes from the workflow transition
        $this->entityManager->expects($this->once())->method('flush');

        $line = sprintf(self::ROW, '100.00', '15.06.2026', '42', 2, 'tx-1');
        $this->createService(new MockHttpClient(new MockResponse($line)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;

        $this->assertSame($this->bankTransferPaymentType, $purchase->getPaymentType());
    }

    public function testAlreadyPaidPurchaseIsPersistedAsSuccessWithoutChangingPaymentType(): void
    {
        $purchase = new Purchase();
        $purchase->assignWorkflowFlag(PWC::F_PAYMENT_SUCCESS->value);

        $this->purchaseRepository->method('find')->willReturn($purchase);
        $this->purchaseFlow->expects($this->never())->method('apply');

        // applyBankTransferPayment returns silently (already paid) → processRecord still persists the purchase
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $line = sprintf(self::ROW, '100.00', '15.06.2026', '42', 2, 'tx-1');
        $this->createService(new MockHttpClient(new MockResponse($line)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;

        $this->assertNull($purchase->getPaymentType());
    }

    public function testWorkflowExceptionLogsFailureAndSkipsPersistingPurchase(): void
    {
        $purchase = new Purchase();
        $this->purchaseFlow->method('apply')->willThrowException(new \RuntimeException('Transition blocked'));
        $this->purchaseRepository->method('find')->willReturn($purchase);

        $this->entityManager->expects($this->exactly(1))->method('persist'); // failure PaymentAction only
        $this->entityManager->expects($this->once())->method('flush');

        $line = sprintf(self::ROW, '100.00', '15.06.2026', '42', 2, 'tx-1');
        $this->createService(new MockHttpClient(new MockResponse($line)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;

        $this->assertNull($purchase->getPaymentType());
    }

    public function testPendingOrTerminatedPaymentsAreSkipped(): void
    {
        $this->purchaseRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $pending = sprintf(self::ROW, '0.00', '15.06.2026', '42', 0, 'tx-1');
        $terminated = sprintf(self::ROW, '0.00', '15.06.2026', '43', 4, 'tx-2');

        $this->createService(new MockHttpClient(new MockResponse($pending . "\r\n" . $terminated)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    public function testUnmatchedPurchaseIsSkippedWithoutPersisting(): void
    {
        $this->purchaseRepository->expects($this->once())->method('find')->with('99')->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $line = sprintf(self::ROW, '100.00', '15.06.2026', '99', 2, 'tx-1');
        $this->createService(new MockHttpClient(new MockResponse($line)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    public function testMalformedRowIsSkipped(): void
    {
        $this->purchaseRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->atLeastOnce())->method('warning');

        $this->createService(new MockHttpClient(new MockResponse('too;short;row')))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    public function testRowWithUnparsableDateIsSkipped(): void
    {
        $this->purchaseRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->atLeastOnce())->method('warning');

        $line = sprintf(self::ROW, '100.00', 'not-a-date', '42', 2, 'tx-1');
        $this->createService(new MockHttpClient(new MockResponse($line)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    public function testMultipleCompletedRecordsAreAllProcessedInOneBatch(): void
    {
        $purchase1 = new Purchase();
        $purchase2 = new Purchase();

        $this->purchaseRepository->method('find')
            ->willReturnOnConsecutiveCalls($purchase1, $purchase2)
        ;

        $this->entityManager->expects($this->exactly(2))->method('persist'); // 2× Purchase
        $this->entityManager->expects($this->once())->method('flush');

        $line1 = sprintf(self::ROW, '50.00', '15.06.2026', '11', 2, 'tx-a');
        $line2 = sprintf(self::ROW, '75.00', '16.06.2026', '22', 2, 'tx-b');

        $this->createService(new MockHttpClient(new MockResponse($line1 . "\n" . $line2)))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    public function testRequestUsesConfiguredCredentialsAndColumnLayout(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('shopname=SHOP', $url);
            $this->assertStringContainsString('password=SECRET', $url);
            $this->assertStringContainsString('creditaccount=123', $url);
            $this->assertStringContainsString('creditbank=5500', $url);
            $this->assertStringContainsString('listtype=PLAIN', $url);
            $this->assertStringContainsString('showid=Y', $url);
            $this->assertStringContainsString('paidfrom=01.06.2026', $url);

            return new MockResponse('');
        });

        $this->createService($httpClient)->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testThrowsAndLogsCriticalWhenBankTransferPaymentTypeIsNotConfigured(): void
    {
        $this->paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $this->paymentTypeRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->logger->expects($this->once())->method('critical');

        $this->expectException(\RuntimeException::class);

        $this->createService(new MockHttpClient(new MockResponse('')))
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    public function testThrowsAndLogsCriticalWhenHttpRequestFails(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('connection refused');
        });

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->logger->expects($this->once())->method('critical');

        $this->expectException(\Throwable::class);

        $this->createService($httpClient)->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testCriticalLogRedactsPasswordFromExceptionMessage(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('https://online.rb.cz/...?password=SECRET&... failed');
        });

        $this->logger->expects($this->once())
            ->method('critical')
            ->with($this->anything(), $this->callback(function (array $context) {
                $this->assertArrayNotHasKey('exception', $context);
                $this->assertStringNotContainsString('SECRET', $context['exception_message']);
                $this->assertStringContainsString('***', $context['exception_message']);
                return true;
            }))
        ;

        $this->expectException(\Throwable::class);

        $this->createService($httpClient)->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testDoesNothingWhenIntegrationIsDisabled(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('HTTP client must not be called when the integration is disabled.');
        });

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->logger->expects($this->once())->method('info');

        $this->createService($httpClient, enabled: false)
            ->downloadAndProcessPayments(new \DateTime('2026-06-01'))
        ;
    }

    private function createService(MockHttpClient $httpClient, bool $enabled = true): RbBankPaymentImportService
    {
        return new RbBankPaymentImportService(
            $httpClient,
            $this->entityManager,
            $this->purchaseRepository,
            $this->paymentTypeRepository,
            new ManagePurchase(
                $this->createMock(CurrencyManager::class),
                $this->createMock(PurchasePriceFactory::class),
                $this->createMock(ProductVariantPriceFactory::class),
                $this->purchaseRepository,
                new ManageVies($this->createMock(LoggerInterface::class)),
                $this->createMock(MessageBusInterface::class),
                new ParcelServiceProvider([]),
                $this->purchaseFlow,
            ),
            new PaymentActionLogger($this->entityManager),
            $this->logger,
            $enabled,
            'SHOP',
            '123',
            '5500',
            'SECRET',
        );
    }
}
