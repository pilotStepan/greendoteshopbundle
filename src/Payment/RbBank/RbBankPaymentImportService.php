<?php

namespace Greendot\EshopBundle\Payment\RbBank;

use Throwable;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
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
        #[\SensitiveParameter]
        private string                 $password,
    ) {}

    public function downloadAndProcessPayments(\DateTimeInterface $startDate): void
    {
        if (!$this->enabled) {
            $this->logger->info('RB bank payment integration is disabled, skipping import.');
            return;
        }

        try {
            $rawList = $this->fetchPaymentsList($startDate);
            $paymentType = $this->resolveBankTransferPaymentType();

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

    private function resolveBankTransferPaymentType(): PaymentType
    {
        $paymentType = $this->paymentTypeRepository->findOneBy([
            'action_group' => PaymentTypeActionGroup::BANK_TRANSFER,
            'account' => $this->account,
            'bank_number' => $this->bankCode,
        ]);

        if (!$paymentType) {
            throw new RuntimeException(sprintf(
                'No bank-transfer PaymentType configured for RB account %s/%s.',
                $this->account,
                $this->bankCode,
            ));
        }

        return $paymentType;
    }

    private function processRecord(RbBankPaymentRecord $record, PaymentType $paymentType): void
    {
        $purchase = $this->purchaseRepository->find($record->variableSymbol);
        if (!$purchase) {
            return;
        }

        try {
            $this->managePurchase->applyBankTransferPayment($purchase, $paymentType);
        } catch (\Throwable $e) {
            $this->logOutcome('failure', sprintf(
                'Platbu pro objednávku #%d (VS %s) se nepodařilo potvrdit. Error: %s',
                $purchase->getId(),
                $record->variableSymbol,
                $e->getMessage(),
            ), $record);
            return;
        }

        $this->entityManager->persist($purchase);
        $this->logOutcome('success', sprintf(
            'Objednávka #%d (VS %s) byla potvrzena jako zaplacená bankovním převodem.',
            $purchase->getId(),
            $record->variableSymbol,
        ), $record);
    }

    private function logOutcome(string $name, string $description, RbBankPaymentRecord $record): void
    {
        $paymentAction = (new PaymentAction())
            ->setName($name)
            ->setDescription($description)
            ->setDate(new \DateTime())
            ->setPerformedBy('system')
            ->setData(sprintf(
                'VS=%s; transactionId=%s; amount=%.2f %s',
                $record->variableSymbol,
                $record->transactionId ?? '',
                $record->transferredAmount,
                $record->currencyCode,
            ))
        ;

        $this->entityManager->persist($paymentAction);
    }

    private function fetchPaymentsList(\DateTimeInterface $startDate): string
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

        return new RbBankPaymentRecord(
            validFrom: \DateTimeImmutable::createFromFormat('d.m.Y', trim($columns[0])),
            validTo: \DateTimeImmutable::createFromFormat('d.m.Y', trim($columns[1])),
            prescribedAmount: (float)$columns[2],
            currencyCode: trim($columns[3]),
            transferredAmount: (float)$columns[4],
            transferDate: \DateTimeImmutable::createFromFormat('d.m.Y', trim($columns[5])),
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
