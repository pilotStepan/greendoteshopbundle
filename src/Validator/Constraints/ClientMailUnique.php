<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ClientMailUnique extends Constraint
{
    public $message = 'The e-mail is already in use.';
}