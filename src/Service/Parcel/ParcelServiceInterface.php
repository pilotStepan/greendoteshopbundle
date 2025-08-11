<?php

namespace Greendot\EshopBundle\Service\Parcel;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\TransportationAPI;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for parcel services.
 * Defines methods for creating parcels, retrieving parcel statuses,
 * and checking if the service supports a specific transportation ID.
 */
#[AutoconfigureTag('app.parcel_service')]
interface ParcelServiceInterface
{
    /**
     * Creates a parcel for the given purchase.
     * @return string|null The parcel id, or null if creation fails.
     */
    public function createParcel(Purchase $purchase): ?string;

    /**
     * Retrieves the status of a parcel for the given purchase.
     * @return array|null The parcel status as an array, or null if unavailable.
     */
    public function getParcelStatus(Purchase $purchase): ?array;

    /**
     * Checks if the service supports the given transportationAPI enum.
     * Used in ParcelServiceProvider.
     */
    public function supports(TransportationAPI $transportationAPI): bool;
}