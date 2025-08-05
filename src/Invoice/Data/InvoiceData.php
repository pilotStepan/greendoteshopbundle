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
        public bool                         $isVatExempted,
        public ?string                      $invoiceNumber,
        public ?DateTime                    $dateInvoiced,
        public ?DateTime                    $dateDue,
        // public InvoicePersonData            $contractor,
        public InvoicePersonData            $customer,
        public InvoicePaymentData           $payment,
        public ?string                      $qrPath,
        public InvoiceTransportationData    $transportation,
        public Currency                     $currencyPrimary,
        public Currency                     $currencySecondary,
        /** @var InvoiceItemData[] */
        public array                        $items,
        /** @var VatCategoryData */
        public array                        $vatCategories,

        // purchase prices
        public float                        $totalPriceNoVat,
        public float                        $totalPriceNoVatSecondary,

        public float                        $totalPriceVat,
        public float                        $totalPriceVatSecondary,

        public float                        $totalPriceNoVatNoDiscount,
        public float                        $totalPriceNoVatNoDiscountSecondary,

        public float                        $totalPriceVatNoDiscount,
        public float                        $totalPriceVatNoDiscountSecondary,

        public float                        $discountPercentage,
        public float                        $discountValue,
        public float                        $discountValueSecondary,

        public float                        $voucherValue,
        public float                        $voucherValueSecondary,
    ) {}
}