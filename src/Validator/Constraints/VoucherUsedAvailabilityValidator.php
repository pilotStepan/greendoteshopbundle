<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class VoucherUsedAvailabilityValidator extends ConstraintValidator
{

    public function __construct(ManageClientDiscount $manageClientDiscount)
    {
    }

    public function validate(mixed $voucher, Constraint $constraint): void
    {
        $now = new \DateTime();
        if($voucher !== null && $now <= $voucher->getDateUntil() ){
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}