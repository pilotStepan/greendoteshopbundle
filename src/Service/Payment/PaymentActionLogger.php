<?php

namespace Greendot\EshopBundle\Service\Payment;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentAction;

readonly class PaymentActionLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function log(
        Purchase $purchase,
        string   $name,
        string   $performedBy,
        ?string  $description = null,
        array    $data = [],
        ?Payment $payment = null,
    ): PaymentAction
    {
        $action = (new PaymentAction())
            ->setPurchase($purchase)
            ->setPayment($payment)
            ->setName($name)
            ->setPerformedBy($performedBy)
            ->setDescription($description)
            ->setDate(new \DateTime())
            ->setData(json_encode($data))
        ;

        $this->entityManager->persist($action);

        return $action;
    }
}
