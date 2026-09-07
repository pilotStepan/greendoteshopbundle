<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Money\Exception;

/**
 * Thrown when an operation combines two Money values with different ISO currency codes.
 */
class CurrencyMismatchException extends \InvalidArgumentException
{
    public function __construct(string $expectedIso, string $actualIso)
    {
        parent::__construct(sprintf(
            'Cannot combine Money values of different currencies: "%s" vs "%s".',
            $expectedIso,
            $actualIso,
        ));
    }
}
