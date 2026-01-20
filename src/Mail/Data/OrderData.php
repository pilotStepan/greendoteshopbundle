<?php

namespace Greendot\EshopBundle\Mail\Data;

class OrderData
{
    public function __construct(
        public int                     $purchaseId,
        public bool                    $vatExempted,
        public ?string                 $qrCodeUri,
        public ?string                 $payLink,
        public ?string                 $trackingUrl,
        public ?string                 $trackingNumber,
        public ?string                 $purchaseNote,
        public OrderTransportationData $transportation,
        public OrderPaymentData        $payment,
        /** @var $addresses array{billing: OrderAddressData, shipping?: OrderAddressData} */
        public array                   $addresses,
        /* @var OrderItemData[] */
        public array                   $items,
        /** @var 'czk'|'eur' */
        public string                  $primaryCurrency,
        public bool                    $orderPaid,
        public string                  $totalPriceCzk,
        public string                  $totalPriceEur,
        public string                  $clientSectionUrl,
    ) {}
}