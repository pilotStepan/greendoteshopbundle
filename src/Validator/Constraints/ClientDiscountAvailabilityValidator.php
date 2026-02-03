<?php

namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;

final class ClientDiscountAvailabilityValidator extends ConstraintValidator
{
    /**
     * @param ClientDiscount             $value
     * @param ClientDiscountAvailability $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($value !== null && !$value->isValid()) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}