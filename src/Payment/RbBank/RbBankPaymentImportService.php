<?php

namespace Greendot\EshopBundle\Payment\RbBank;

use DateTimeImmutable;
use DateTimeInterface;
use Greendot\EshopBundle\Money\Money;
use SensitiveParameter;
use Throwable;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Enum\PaymentActionType;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;

/**
 * Downloads and reconciles Raiffeisenbank's "Modul platebního systému" payments-list
 * report (https://online.rb.cz/ibs/eshop/payments-list) against open purchases.
 *
 */
readonly class RbBankPaymentImportService
{
    private const URL = 'https://online.rb.cz/ibs/eshop/payments-list';

    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $entityManager,
        private PurchaseRepository     $purchaseRepository,
        private PaymentTypeRepository  $paymentTypeRepository,
        private ManagePurchase         $managePurchase,
        private PaymentActionLogger    $paymentActionLogger,
        private LoggerInterface        $logger,
        #[Autowire(param: 'greendot_eshop.payment.rb_bank.enabled')]
        private bool                   $enabled,
        #[Autowire(param: 'greendot_eshop.payment.rb_bank.shopname')]
        private string                 $shopname,
        #[Autowire(param: 'greendot_eshop.payment.rb_bank.account')]
        private string                 $account,
        #[Autowire(param: 'greendot_eshop.payment.rb_bank.bank_code')]
        private string                 $bankCode,
        #[Autowire(param: 'greendot_eshop.payment.rb_bank.password')]
        #[SensitiveParameter]
        private string                 $password,
    ) {}

    public function downloadAndProcessPayments(DateTimeInterface $startDate): void
    {
        if (!$this->enabled) {
            $this->logger->info('RB bank payment integration is disabled, skipping import.');
            return;
        }

        try {
            $rawList = $this->fetchPaymentsList($startDate);
            $paymentType = $this->findBankTransferPaymentType();

            foreach ($this->parsePaymentsList($rawList) as $record) {
                if ($record->status !== RbPaymentStatus::Completed) {
                    continue;
                }

                $this->processRecord($record, $paymentType);
            }

            $this->entityManager->flush();
        } catch (Throwable $e) {
            $this->logger->critical('RB bank payment import failed', [
                'startDate' => $startDate->format('d.m.Y'),
                'exception_class' => $e::class,
                'exception_message' => $this->password !== ''
                    ? str_replace($this->password, '***', $e->getMessage())
                    : $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function findBankTransferPaymentType(): ?PaymentType
    {
        return $this->paymentTypeRepository->findOneBy([
            'action_group' => PaymentTypeActionGroup::BANK_TRANSFER,
            'account' => $this->account,
            'bank_number' => $this->bankCode,
        ]) ?? throw new RuntimeException(sprintf(
            'No bank-transfer PaymentType configured for RB account %s/%s.',
            $this->account,
            $this->bankCode,
        ));
    }

    private function processRecord(RbBankPaymentRecord $record, PaymentType $paymentType): void
    {
        $purchase = $this->purchaseRepository->find($record->variableSymbol);
        if (!$purchase) {
            return;
        }

        if ($record->debitAccountNumber === '160987123' && $record->debitBankCode === '0300') {
            return; // CESKA POSTA COD remittance, not a customer transfer
        }

        $this->managePurchase->preparePrices($purchase);
        $expectedMoney = $purchase->getTotalMoney();
        $transferredMoney = $record->transferredMoney;

        if (!$expectedMoney->isSameCurrency($transferredMoney)) {
            $this->paymentActionLogger->log($purchase, PaymentActionType::FAILURE->value, 'system',
                sprintf('Platba pro objednávku #%d (VS %s) přišla v jiné měně (%s) než objednávka (%s); platba nebyla potvrzena.',
                    $purchase->getId(),
                    $record->variableSymbol,
                    $transferredMoney->iso,
                    $expectedMoney->iso,
                ),
                [
                    'source' => 'rb_bank',
                    'variableSymbol' => $record->variableSymbol,
                    'transactionId' => $record->transactionId,
                    'transferredMoney' => $transferredMoney,
                    'expectedMoney' => $expectedMoney,
                ],
            );
            return;
        }

        if ($transferredMoney->lessThan($expectedMoney)) {
            $this->paymentActionLogger->log($purchase, PaymentActionType::FAILURE->value, 'system',
                sprintf(
                    'Přijatá částka %s je nižší než cena objednávky #%d (%s); platba nebyla potvrzena.',
                    $transferredMoney,
                    $purchase->getId(),
                    $expectedMoney,
                ),
                [
                    'source' => 'rb_bank',
                    'variableSymbol' => $record->variableSymbol,
                    'transactionId' => $record->transactionId,
                    'transferredMoney' => $transferredMoney,
                    'expectedMoney' => $expectedMoney,
                ],
            );
            return;
        }

        try {
            $this->managePurchase->applyBankTransferPayment($purchase, $paymentType, [
                'performed_by' => 'system',
                'source' => 'rb_bank',
                'variableSymbol' => $record->variableSymbol,
                'transactionId' => $record->transactionId,
                'transferredMoney' => $transferredMoney,
                'expectedMoney' => $expectedMoney,
            ]);
        } catch (Throwable $e) {
            $this->paymentActionLogger->log($purchase, PaymentActionType::FAILURE->value, 'system',
                sprintf('Platbu pro objednávku #%d (VS %s) se nepodařilo potvrdit. Error: %s', $purchase->getId(), $record->variableSymbol, $e->getMessage()),
                [
                    'source' => 'rb_bank',
                    'variableSymbol' => $record->variableSymbol,
                    'transactionId' => $record->transactionId,
                    'transferredMoney' => $transferredMoney,
                ],
            );
            return;
        }

        $this->entityManager->persist($purchase);
    }

    private function fetchPaymentsList(DateTimeInterface $startDate): string
    {
        $response = $this->httpClient->request('GET', self::URL, [
            'query' => [
                'shopname' => $this->shopname,
                'password' => $this->password,
                'creditaccount' => $this->account,
                'creditbank' => $this->bankCode,
                'paidfrom' => $startDate->format('d.m.Y'),
                'listtype' => 'PLAIN',
                'showproduct' => 'N',
                'showaccname' => 'N',
                'showspecsymbol' => 'N',
                'showid' => 'Y',
                'cash' => 'N',
            ],
        ]);

        return $response->getContent();
    }

    /** @return RbBankPaymentRecord[] */
    private function parsePaymentsList(string $rawList): array
    {
        $records = [];
        foreach (preg_split('/\R/', trim($rawList)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $record = $this->parseLine($line);
            if ($record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = ltrim(trim($value), "\u{FEFF}");
        foreach (['d.m.Y H:i:s', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }
        return null;
    }

    private function parseLine(string $line): ?RbBankPaymentRecord
    {
        $columns = str_getcsv($line, ';');
        if (count($columns) < 14) {
            $this->logger->warning('Skipping malformed RB bank payment row', ['line' => $line]);
            return null;
        }

        $status = RbPaymentStatus::tryFrom((int)$columns[13]);
        if (!$status) {
            $this->logger->warning('Skipping RB bank payment row with unknown status', ['line' => $line]);
            return null;
        }

        $validFrom = $this->parseDate($columns[0]);
        $validTo = $this->parseDate($columns[1]);
        $transferDate = $this->parseDate($columns[5]);
        if ($validFrom === null || $validTo === null || $transferDate === null) {
            $this->logger->warning('Skipping RB bank payment row with unparsable date', ['line' => $line]);
            return null;
        }

        return new RbBankPaymentRecord(
            validFrom: $validFrom,
            validTo: $validTo,
            prescribedAmount: (float)$columns[2],
            transferredMoney: new Money((float)$columns[4], strtoupper(trim($columns[3]))),
            transferDate: $transferDate,
            debitAccountNumber: trim($columns[6]),
            debitBankCode: trim($columns[7]),
            creditAccountNumber: trim($columns[8]),
            creditBankCode: trim($columns[9]),
            variableSymbol: trim($columns[10]),
            constantSymbol: trim($columns[11]),
            note: trim($columns[12]),
            status: $status,
            transactionId: isset($columns[14]) && trim($columns[14]) !== '' ? trim($columns[14]) : null,
        );
    }
}
