<?php

namespace Greendot\EshopBundle\Parcel\Exception;

/**
 * Thrown when a parcel operation fails with a permanent carrier/data error that
 * cannot be resolved by retrying (invalid address, closed pickup point, bad credentials,
 * missing required local data). The message should be dispatched to the failed transport
 * and surfaced for operator action.
 */
class PermanentParcelException extends \RuntimeException {}
