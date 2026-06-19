<?php

namespace Greendot\EshopBundle\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;

interface ParcelServiceProviderInterface
{
    /**
     * Retrieves the parcel service that supports the given transportation API.
     *
     * @param TransportationAPI $transportationAPI The transportationAPI to find a service for.
     * @return ParcelServiceInterface The parcel service that supports the transportation API.
     * @throws ParcelServiceNotFoundException If no parcel service supports this transportation API.
     */
    public function get(TransportationAPI $transportationAPI): ParcelServiceInterface;

    /**
     * Retrieves the parcel service for a given purchase.
     * Uses the transportationAPI enum from the purchase.transportation to find the appropriate service.
     *
     * @param Purchase $purchase The purchase to get the parcel service for.
     * @return ParcelServiceInterface The parcel service that supports the transportation API.
     * @throws ParcelServiceNotFoundException If no parcel service supports this purchase's transportation.
     */
    public function getByPurchase(Purchase $purchase): ParcelServiceInterface;
}
