<?php

namespace Greendot\EshopBundle\Parcel;

use DateTimeInterface;

class ParcelStatusInfoDto
{
    public function __construct(
        /* State of the delivery provided by the parcel service */
        public ParcelDeliveryStateEnum $state,
        /* Any additional details about the parcel status with orbitary structure */
        public array                   $details = [],
        /* When the carrier says it happened (nullable if unknown) */
        public ?DateTimeInterface      $occurredAt = null,
    ) {}
}