<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ClientMailUnique extends Constraint
{
    public string $message = 'Email "{{ email }}" je již registrován';
}