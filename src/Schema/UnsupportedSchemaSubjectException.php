<?php

namespace Greendot\EshopBundle\Schema;

use Throwable;
use Exception;

final class UnsupportedSchemaSubjectException extends Exception
{
    public function __construct(
        string     $message = 'Schema provider does not support this object.',
        int        $code = 0,
        ?Throwable $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
