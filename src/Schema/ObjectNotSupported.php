<?php

namespace App\Schema;

final class ObjectNotSupported extends \Exception
{
    public function __construct(
        string      $message = 'Schema provider does not support this object.',
        int         $code = 0,
        ?\Throwable $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
