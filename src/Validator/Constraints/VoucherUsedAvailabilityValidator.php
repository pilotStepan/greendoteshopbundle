<?php

namespace Greendot\EshopBundle\Validator\Constraints;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Greendot\EshopBundle\Entity\Project\Voucher;

final class VoucherUsedAvailabilityValidator extends ConstraintValidator
{

    public function __construct(ManageClientDiscount $manageClientDiscount)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value) return;

        /* @var $value Collection<int, Voucher> */
        $now = new \DateTime();
        foreach ($value as $voucher) {
            if ($now <= $voucher->getDateUntil()) {
                $this->context->buildViolation($constraint->message)->addViolation();
                return;
            }
        }
    }
}