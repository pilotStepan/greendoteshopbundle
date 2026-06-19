<?php

namespace Greendot\EshopBundle\Payment\RbBank;

// one row of Raiffeisenbank's PLAIN payments-list report, see RbBankPaymentImportService for the column layout
use DateTimeImmutable;

readonly class RbBankPaymentRecord
{
    public function __construct(
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validTo,
        public float             $prescribedAmount,
        public string            $currencyCode,
        public float             $transferredAmount,
        public DateTimeImmutable $transferDate,
        public string            $debitAccountNumber,
        public string            $debitBankCode,
        public string            $creditAccountNumber,
        public string            $creditBankCode,
        public string            $variableSymbol,
        public string            $constantSymbol,
        public string            $note,
        public RbPaymentStatus   $status,
        public ?string           $transactionId,
    ) {}
}
