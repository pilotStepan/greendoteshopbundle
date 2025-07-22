<?php

namespace Greendot\EshopBundle\Invoice\Data;

use DateTime;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Tests\Service\Price\PurchasePriceDataProvider;

class InvoiceData
{
    public function __construct(
        public ?string                      $invoiceId,
        public int                          $purchaseId,
        public bool                         $isInvoice,
        public ?DateTime                    $dateInvoiced,
        public DateTime                     $dateDue,
        // public InvoicePersonData            $contractor,
        public InvoicePersonData            $customer,
        public InvoicePaymentData           $payment,
        public ?string                      $qrPath,
        public InvoiceTransportationData    $transportation,
        public Currency                     $currencyPrimary,
        public Currency                     $currencySecondary,
        /** @var InvoiceItemData[] */
        public array                        $items,
        public float                        $totalPriceNoVatNoDiscount,
        public float                        $totalPriceNoVatNoDiscountSecondary,
        public float                        $vat,
        public float                        $vatSecondary,
        public float                        $totalPriceNoDiscount,
        public float                        $totalPriceNoDiscountSecondary,
        public float                        $discountPercentage,
        public float                        $discountValue,
        public float                        $discountValueSecondary,
        public float                        $voucherValue,
        public float                        $voucherValueSecondary,
        public float                        $totalPrice,
        public float                        $totalPriceSecondary,
    ) {}
}