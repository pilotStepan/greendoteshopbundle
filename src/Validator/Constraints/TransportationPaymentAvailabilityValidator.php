<?php
namespace Greendot\EshopBundle\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class TransportationPaymentAvailabilityValidator extends ConstraintValidator
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function validate($purchase, Constraint $constraint)
    {
        if (!$purchase instanceof Purchase) {
            return;
        }

        $transportation = $purchase->getTransportation();
        $paymentType = $purchase->getPaymentType();

        // Check if there is a valid link between transportation and payment type
        if ($transportation->getPaymentTypes()->contains($paymentType)) {
            return; // Found a valid link, no need to continue
        }

        // If no valid link was found, add a violation
        $this->context->buildViolation($constraint->message)->addViolation();
    }
}