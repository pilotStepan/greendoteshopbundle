<?php

namespace Greendot\EshopBundle\Service\Parcel;

/**
 * Provides parcel services based on the transportation ID.
 * Iterates through available parcel services to find one that supports the given transportation ID.
 */
class ParcelServiceProvider
{
    /* @var iterable|ParcelServiceInterface[] */
    private iterable $parcelServices;

    /* Symfony DI will inject all services that implement ParcelServiceInterface */
    public function __construct(iterable $parcelServices)
    {
        $this->parcelServices = $parcelServices;
    }

    /**
     * Retrieves the parcel service that supports the given transportation ID.
     *
     * @param int $transportationId The transportation ID to find a service for.
     * @return ParcelServiceInterface The parcel service that supports the transportation ID.
     */
    public function get(int $transportationId): ParcelServiceInterface
    {
        /* @var ParcelServiceInterface[] $service */
        foreach ($this->parcelServices as $service) {
            if ($service->supports($transportationId)) {
                return $service;
            }
        }

        throw new \InvalidArgumentException('No service found for transportation ID: ' . $transportationId);
    }
}
