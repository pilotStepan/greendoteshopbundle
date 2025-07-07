<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ClientDiscountAvailability extends Constraint
{
    public $message = 'Kupón není platný.';
}