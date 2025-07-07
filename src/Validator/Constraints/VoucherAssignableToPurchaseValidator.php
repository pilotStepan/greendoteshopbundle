<?php

namespace Greendot\EshopBundle\Validator\Constraints;

use Greendot\EshopBundle\Entity\Project\Voucher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class VoucherAssignableToPurchaseValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) return;

        /** @var Voucher $voucher */
        $voucher = $this->context->getObject();

        // Check if the voucher is already used in another purchase
        if ($voucher->getPurchaseUsed() !== null && $voucher->getPurchaseUsed() !== $value) {
            $this->context->buildViolation($constraint->message)->addViolation();
            return;
        }

        // Check if the voucher is expired
        $now = new \DateTimeImmutable();
        $expiry = $voucher->getDateUntil();
        if ($expiry !== null && $now > $expiry) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }

        // Check if the voucher is not paid
        if ($voucher->getState() !== 'paid') {
            $this->context->buildViolation($constraint->message)->addViolation();
        }

        // Check if the purchase is not in draft state (cart state)
        if ($value->getState() !== 'draft') {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}