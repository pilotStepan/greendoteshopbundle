<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\TransportationAPI;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Provides parcel services based on the transportation ID.
 * Iterates through available parcel services to find one that supports the given transportation ID.
 */
readonly class ParcelServiceProvider
{
    /* @var ParcelServiceInterface[] $parcelServices */
    private iterable $parcelServices;

    /* Symfony DI will inject all services that implement ParcelServiceInterface (defined in services.yaml) */
    public function __construct(
        #[AutowireIterator('app.parcel_service')]
        iterable $parcelServices,
    )
    {
        $this->parcelServices = $parcelServices;
    }

    /**
     * Retrieves the parcel service that supports the given transportation API.
     *
     * @param TransportationAPI $transportationAPI The transportationAPI to find a service for.
     * @return ?ParcelServiceInterface The parcel service that supports the transportation API.
     */
    public function get(TransportationAPI $transportationAPI): ?ParcelServiceInterface
    {
        /* @var ParcelServiceInterface[] $service */
        foreach ($this->parcelServices as $service) {
            if ($service->supports($transportationAPI)) {
                return $service;
            }
        }
        return null;
    }

    /**
     * Retrieves the parcel service for a given purchase.
     * Uses the transportationAPI enum from the purchase.transportation to find the appropriate service.
     *
     * @param Purchase $purchase The purchase to get the parcel service for.
     * @return ?ParcelServiceInterface The parcel service for the purchase, or null if not found.
     */
    public function getByPurchase(Purchase $purchase): ?ParcelServiceInterface
    {
        $transportationAPI = $purchase->getTransportation()?->getTransportationAPI();
        return $transportationAPI ? $this->get($transportationAPI) : null;
    }
}
