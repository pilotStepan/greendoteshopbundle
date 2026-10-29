<?php

namespace Greendot\EshopBundle\Service\PaymentGateway\GoPay;

use RuntimeException;

class GoPayException extends RuntimeException
{
    /**
     * @param array<int, array<string, mixed>> $errors GoPay `errors` array from the error response body
     */
    public function __construct(
        string                 $message,
        private readonly array $errors = [],
        int                    $code = 0,
        ?\Throwable            $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
