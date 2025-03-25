<?php

namespace Greendot\EshopBundle\Validator\Constraints;

use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ClientMailUniqueValidator extends ConstraintValidator
{
    public function __construct(private readonly ClientRepository $clientRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value) return;

        $existingClient = $this->clientRepository->findNonAnonymousByEmail($value);

        if ($existingClient) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ email }}', $value)
                ->addViolation();
        }
    }
}