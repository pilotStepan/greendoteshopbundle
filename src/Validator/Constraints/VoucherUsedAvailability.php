<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class VoucherUsedAvailability extends Constraint
{
    public $message = 'The voucher is not valid.';
}