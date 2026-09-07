<?php

namespace Greendot\EshopBundle\Invoice\Data;

use DateTime;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Money\Money;

class InvoiceData
{
    public function __construct(
        public ?string                      $invoiceId,
        public int                          $purchaseId,
        public bool                         $isInvoice,
        public bool                         $isVatExempted,
        public ?string                      $invoiceNumber,
        public ?string                      $internalNumber,
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

        public ?ClientDiscount              $purchaseDiscount,
        public float                        $discountPercentage,
        public float                        $discountValue,
        public float                        $discountValueSecondary,

        public array                        $vouchersUsed,
        public float                        $voucherValue,
        public float                        $voucherValueSecondary,
        public float                        $toPayVatCzk,
        public float                        $toPayVatEur,
        public float                        $toPayNoVatCzk,
        public float                        $toPayNoVatEur,

        public ?Money                       $toPayVatMoney = null,
        public ?Money                       $toPayNoVatMoney = null,

        /**
         * Secondary-currency counterpart of toPayVatMoney/toPayNoVatMoney: whichever of
         * (shop default, configured secondary currency) the primary isn't. See
         * CurrencyManager::getSecondaryFor().
         */
        public ?Money                       $toPayVatMoneySecondary = null,
        public ?Money                       $toPayNoVatMoneySecondary = null,

        /**
         * Money-typed twins of the totalPrice / discountValue / voucherValue fields above, in
         * the purchase's own currency paired with the correct secondary currency instead of
         * always CZK/EUR.
         */
        public ?Money                       $totalPriceNoVatMoney = null,
        public ?Money                       $totalPriceNoVatMoneySecondary = null,
        public ?Money                       $totalPriceVatMoney = null,
        public ?Money                       $totalPriceVatMoneySecondary = null,
        public ?Money                       $totalPriceNoVatNoDiscountMoney = null,
        public ?Money                       $totalPriceNoVatNoDiscountMoneySecondary = null,
        public ?Money                       $totalPriceVatNoDiscountMoney = null,
        public ?Money                       $totalPriceVatNoDiscountMoneySecondary = null,
        public ?Money                       $discountValueMoney = null,
        public ?Money                       $discountValueMoneySecondary = null,
        public ?Money                       $voucherValueMoney = null,
        public ?Money                       $voucherValueMoneySecondary = null,
    ) {}
}