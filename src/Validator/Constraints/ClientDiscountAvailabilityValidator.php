<?php

namespace Greendot\EshopBundle\Validator\Constraints;

use Greendot\EshopBundle\Service\ManageClientDiscount;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ClientDiscountAvailabilityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManageClientDiscount $manageClientDiscount
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($value !== null && !$this->manageClientDiscount->isValid($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}