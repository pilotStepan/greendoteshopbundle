<?php

namespace Greendot\EshopBundle\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;

/**
 * Provides parcel services based on the transportation ID.
 * Iterates through available parcel services to find one that supports the given transportation ID.
 */
readonly class ParcelServiceProvider implements ParcelServiceProviderInterface
{
    /* @var ParcelServiceInterface[] $parcelServices */
    private iterable $parcelServices;

    public function __construct(#[AutowireIterator('app.parcel_service')] iterable $parcelServices)
    {
        $this->parcelServices = $parcelServices;
    }

    public function get(TransportationAPI $transportationAPI): ParcelServiceInterface
    {
        /* @var ParcelServiceInterface[] $service */
        foreach ($this->parcelServices as $service) {
            if ($service->supports($transportationAPI)) {
                return $service;
            }
        }
        throw new ParcelServiceNotFoundException(
            sprintf('No parcel service found for transportation API %s', $transportationAPI->value),
        );
    }

    public function getByPurchase(Purchase $purchase): ParcelServiceInterface
    {
        if (!$transportation = $purchase->getTransportation()) {
            throw new ParcelServiceNotFoundException(
                sprintf('Purchase with id %d does not have transportation defined', $purchase->getId()),
            );
        }

        if (!$transportationAPI = $transportation->getTransportationAPI()) {
            throw new ParcelServiceNotFoundException(
                sprintf('Transportation with id %d does not have transportation API defined', $transportation->getId()),
            );
        }

        return $this->get($transportationAPI);
    }
}
