<?php

namespace Greendot\EshopBundle\Dto;

use DateTimeInterface;
use Greendot\EshopBundle\Enum\ParcelDeliveryState;

/**
 * Represents the status of a parcel.
 * This DTO is used to generalize the status and details of a parcel as provided by the parcel service.
 */
class ParcelStatusInfo
{
    public function __construct(
        /* State of the delivery provided by the parcel service */
        public ParcelDeliveryState $state,
        /* Any additional details about the parcel status with orbitary structure */
        public array               $details = [],
        /* When the carrier says it happened (nullable if unknown) */
        public ?DateTimeInterface  $occurredAt = null,
    ) {}
}