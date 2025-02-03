<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ClientDiscountAvailabilityValidator extends ConstraintValidator
{
    private $manageClientDiscount;
    public function __construct(ManageClientDiscount $manageClientDiscount)
    {
        $this->manageClientDiscount = $manageClientDiscount;
    }

    public function validate(ClientDiscount $clientDiscount, Constraint $constraint): void
    {
        if(!$this->manageClientDiscount->isValid($clientDiscount)){
            $this->context->buildViolation($constraint->message)->addViolation();
        }

    }
}