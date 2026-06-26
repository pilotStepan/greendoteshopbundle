<?php

namespace Greendot\EshopBundle\Parcel\Exception;

/**
 * Thrown when a parcel operation fails with a temporary error that may succeed on retry
 * (network timeout, 5xx response, unexpected API response shape, unknown carrier fault).
 */
class TransientParcelException extends \RuntimeException {}
