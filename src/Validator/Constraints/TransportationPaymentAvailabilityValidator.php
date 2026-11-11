<?php

namespace Greendot\EshopBundle\Validator\Constraints;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\ConstraintValidator;

final class TransportationPaymentAvailabilityValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof TransportationPaymentAvailability) {
            throw new UnexpectedTypeException($constraint, TransportationPaymentAvailability::class);
        }

        if (!$value instanceof Purchase) {
            return;
        }

        $transportation = $value->getTransportation();
        $paymentType = $value->getPaymentType();

        if ($transportation === null || $paymentType === null) {
            return;
        }

        if ($transportation->getPaymentTypes()->contains($paymentType)) {
            return; // Found a valid link, no need to continue
        }

        // If no valid link was found, add a violation
        $this->context->buildViolation($constraint->message)->addViolation();
    }
}