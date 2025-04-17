<?php

namespace Greendot\EshopBundle\Service\Parcel;

/**
 * Provides parcel services based on the transportation ID.
 * Iterates through available parcel services to find one that supports the given transportation ID.
 */
readonly class ParcelServiceProvider
{
    /* Symfony DI will inject all services that implement ParcelServiceInterface (defined in services.yaml) */
    public function __construct(
        /* @var ParcelServiceInterface[] $parcelServices */
        private iterable $parcelServices
    )
    {
    }

    /**
     * Retrieves the parcel service that supports the given transportation ID.
     *
     * @param int $transportationId The transportation ID to find a service for.
     * @return ?ParcelServiceInterface The parcel service that supports the transportation ID.
     */
    public function get(int $transportationId): ?ParcelServiceInterface
    {
        /* @var ParcelServiceInterface[] $service */
        foreach ($this->parcelServices as $service) {
            if ($service->supports($transportationId)) {
                return $service;
            }
        }
        return null;
    }
}
