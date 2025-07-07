<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class VoucherAssignableToPurchase extends Constraint
{
    public string $message = 'Poukaz není platný.';
}