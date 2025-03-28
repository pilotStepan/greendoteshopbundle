<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class VoucherUsedAvailability extends Constraint
{
    public string $message = 'The voucher is not valid.';
}