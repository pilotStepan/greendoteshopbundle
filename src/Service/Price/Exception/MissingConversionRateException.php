<?php

namespace Greendot\EshopBundle\Service\Price\Exception;

use Greendot\EshopBundle\Entity\Project\Currency;

/**
 * Thrown when a non-default currency has no ConversionRate valid for the requested date.
 */
class MissingConversionRateException extends \RuntimeException
{
    public function __construct(Currency $currency, \DateTimeInterface $date)
    {
        parent::__construct(sprintf(
            'No conversion rate found for currency "%s" valid on %s.',
            $currency->getName() ?? ('#' . $currency->getId()),
            $date->format('Y-m-d'),
        ));
    }
}
