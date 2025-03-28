<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TransportationPaymentAvailability extends Constraint
{
    public string $message = 'The transportation and payment are not available.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}