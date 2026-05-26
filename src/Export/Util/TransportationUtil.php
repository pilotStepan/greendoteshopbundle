<?php

namespace Greendot\EshopBundle\Export\Util;

use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Enum\PaymentTypeActionGroup;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;

class TransportationUtil
{

    public function __construct(
        private readonly PaymentTypeRepository $paymentTypeRepository
    ){}

    public function getCOD(Transportation $transportation): ?PaymentType
    {
        return $this->paymentTypeRepository->createQueryBuilder('payment_type')
            ->leftJoin('payment_type.transportations', 'transportations')
            ->andWhere('transportations.id = :transportationId')
            ->andWhere('payment_type.action_group = :actionGroup')
            ->andWhere('payment_type.isEnabled = 1')
            ->setParameter('transportationId', $transportation->getId())
            ->setParameter('actionGroup', PaymentTypeActionGroup::ON_DELIVERY->value)
            ->orderBy('payment_type.sequence', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

}