<?php

namespace Greendot\EshopBundle\Service;

use DateTime;
use Greendot\EshopBundle\Entity\Project\Purchase;

class DateService
{
    public function __construct(

    ) { }

    /**
     * Return mutated date with added number of days skipping weekends
     * @param DateTime $date - date to mutate
     * @param int $day - days to add
     */
    public function addWorkDays(DateTime $date, int $days) : DateTime
    {
        $result = clone $date;
        $added = 0;

        while ($added < $days) {
            $result->modify('+1 day');

            // skip weekends
            if (!in_array($result->format('N'), [6, 7])) {
                $added++;
            }
        }

        return $result;
    }

    /**
     * Calculate and set dateDelivery based on dateIssue and transportation.duration
     * @param Purchase $purchase - purchase to mutate
     */
    public function calculatePurchaseDeliveryDate(Purchase $purchase) : Purchase
    {
        $duration = $purchase->getTransportation()->getDuration();

        $deliveryDate = $this->addWorkDays($purchase->getDateIssue(), $duration);

        $purchase->setDateDelivery($deliveryDate);

        return $purchase;
    }
}