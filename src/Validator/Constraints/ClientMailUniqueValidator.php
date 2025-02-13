<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ClientMailUniqueValidator extends ConstraintValidator
{
    private $clientRepository;
    public function __construct(ClientRepository $clientRepository)
    {

        $this->clientRepository = $clientRepository;
    }

    public function validate(mixed $email, Constraint $constraint): void
    {
        if($email !== null && !$this->clientRepository->emailAvailable($email)){
            $this->context->buildViolation($constraint->message)->addViolation();
        }

    }
}