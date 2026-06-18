<?php

namespace Greendot\EshopBundle\Tests\Service\Payment\RbBank;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpClient\Response\MockResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Payment\RbBank\RbBankPaymentImportService;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;

class RbBankPaymentImportServiceTest extends TestCase
{
    private const HEADER_DEFAULTS = '01.06.2026;30.06.2026;100.00;CZK;%s;%s;111111;0100;222222;5500;%s;0;poznamka;%d;%s';

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

        $this->bankTransferPaymentType = new PaymentType();
        $this->paymentTypeRepository->method('findOneBy')
            ->with([
                'action_group' => PaymentTypeActionGroup::BANK_TRANSFER,
                'account' => '123',
                'bank_number' => '5500',
            ])
            ->willReturn($this->bankTransferPaymentType);

        $this->purchaseFlow = $this->createMock(WorkflowInterface::class);
    }

    public function testCompletedPaymentIsMatchedAndConfirmed(): void
    {
        $purchase = new Purchase();
        $this->purchaseFlow->method('can')->willReturn(true);
        $this->purchaseRepository->expects($this->once())->method('find')->with('42')->willReturn($purchase);

        $this->entityManager->expects($this->exactly(3))->method('persist'); // Log + Purchase + success PaymentAction
        $this->entityManager->expects($this->exactly(2))->method('flush'); // audit log flush + final flush

        $line = sprintf(self::HEADER_DEFAULTS, '100.00', '15.06.2026', '42', 2, 'tx-1');
        $service = $this->createService(new MockHttpClient(new MockResponse($line)));

        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));

        $this->assertSame($this->bankTransferPaymentType, $purchase->getPaymentType());
    }

    public function testPendingOrTerminatedPaymentsAreSkipped(): void
    {
        $this->purchaseRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->exactly(1))->method('persist'); // only the audit Log

        $pending = sprintf(self::HEADER_DEFAULTS, '0.00', '15.06.2026', '42', 0, 'tx-1');
        $terminated = sprintf(self::HEADER_DEFAULTS, '0.00', '15.06.2026', '43', 4, 'tx-2');
        $body = $pending . "\r\n" . $terminated;

        $service = $this->createService(new MockHttpClient(new MockResponse($body)));
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testUnmatchedPurchaseIsLoggedAsFailure(): void
    {
        $this->purchaseRepository->expects($this->once())->method('find')->with('99')->willReturn(null);
        $this->entityManager->expects($this->exactly(2))->method('persist'); // audit Log + failure PaymentAction

        $line = sprintf(self::HEADER_DEFAULTS, '100.00', '15.06.2026', '99', 2, 'tx-1');
        $service = $this->createService(new MockHttpClient(new MockResponse($line)));
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testAlreadyPaidPurchaseIsLoggedAsFailure(): void
    {
        $purchase = new Purchase();
        $this->purchaseFlow->method('can')->willReturn(false); // simulates an already-confirmed purchase
        $this->purchaseRepository->method('find')->willReturn($purchase);
        $this->entityManager->expects($this->exactly(2))->method('persist'); // audit Log + failure PaymentAction

        $line = sprintf(self::HEADER_DEFAULTS, '100.00', '15.06.2026', '42', 2, 'tx-1');
        $service = $this->createService(new MockHttpClient(new MockResponse($line)));
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testMalformedRowIsSkipped(): void
    {
        $this->purchaseRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->exactly(1))->method('persist'); // only the audit Log

        $service = $this->createService(new MockHttpClient(new MockResponse('too;short;row')));
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testRequestUsesConfiguredCredentialsAndLocksDownColumnLayout(): void
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

        $service = $this->createService($httpClient);
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testThrowsAndLogsCriticalWhenBankTransferPaymentTypeIsNotConfigured(): void
    {
        $this->paymentTypeRepository = $this->createMock(PaymentTypeRepository::class);
        $this->paymentTypeRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->exactly(1))->method('persist'); // only the audit Log
        $this->logger->expects($this->once())->method('critical');

        $service = $this->createService(new MockHttpClient(new MockResponse('')));

        $this->expectException(\RuntimeException::class);
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testThrowsAndLogsCriticalWhenTheHttpRequestFails(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('connection refused');
        });

        $this->logger->expects($this->once())->method('critical');

        $service = $this->createService($httpClient);

        $this->expectException(\Throwable::class);
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testCriticalLogRedactsPasswordFromExceptionMessage(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('request to https://online.rb.cz/...?password=SECRET&... failed');
        });

        $this->logger->expects($this->once())
            ->method('critical')
            ->with($this->anything(), $this->callback(function (array $context) {
                $this->assertArrayNotHasKey('exception', $context);
                $this->assertStringNotContainsString('SECRET', $context['exception_message']);
                $this->assertStringContainsString('***', $context['exception_message']);
                return true;
            }));

        $service = $this->createService($httpClient);

        $this->expectException(\Throwable::class);
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    public function testDoesNothingWhenIntegrationIsDisabled(): void
    {
        $httpClient = new MockHttpClient(function () {
            $this->fail('HTTP client should not be called when the integration is disabled.');
        });

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->logger->expects($this->once())->method('info');

        $service = $this->createService($httpClient, enabled: false);
        $service->downloadAndProcessPayments(new \DateTime('2026-06-01'));
    }

    private function createService(MockHttpClient $httpClient, bool $enabled = true): RbBankPaymentImportService
    {
        $manageViesLogger = $this->createMock(LoggerInterface::class);

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
                new ManageVies($manageViesLogger),
                $this->createMock(MessageBusInterface::class),
                new ParcelServiceProvider([]),
                $this->purchaseFlow,
            ),
            $this->logger,
            $enabled,
            'SHOP',
            '123',
            '5500',
            'SECRET',
        );
    }
}
