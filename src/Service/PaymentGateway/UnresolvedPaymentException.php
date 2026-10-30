<?php

namespace Greendot\EshopBundle\Service\PaymentGateway;

use RuntimeException;

/**
 * The callback does not belong to any of our payments (unknown or foreign id, malformed request).
 */
class UnresolvedPaymentException extends RuntimeException {}
