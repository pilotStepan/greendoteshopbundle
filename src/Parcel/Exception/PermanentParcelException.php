<?php

namespace Greendot\EshopBundle\Parcel\Exception;

/**
 * Thrown when a parcel operation fails with a permanent carrier/data error that
 * cannot be resolved by retrying (invalid address, closed pickup point, bad credentials,
 * missing required local data). Handlers log it once for operator action and ack the message.
 */
class PermanentParcelException extends \RuntimeException {}
