<?php

namespace Greendot\EshopBundle\Parcel;

use Throwable;
use Greendot\EshopBundle\Entity\Project\Purchase;
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
     * @return string The parcel id
     * @throws Throwable Throw it when service is unavailable or invalid data passed
     */
    public function createParcel(Purchase $purchase): string;

    /**
     * Retrieves the status of a parcel for the given purchase.
     * @param Purchase $purchase
     * @return ParcelStatusInfoDto Dto containing status information
     * @throws Throwable Throw it when service is unavailable or invalid data passed
     */
    public function getParcelStatus(Purchase $purchase): ParcelStatusInfoDto;

    /**
     * Checks if the service supports the given transportationAPI enum.
     * Used in ParcelServiceProvider.
     */
    public function supports(TransportationAPI $transportationAPI): bool;

    /**
     * Whether getParcelStatus() has a chance of succeeding for this purchase, i.e. whether
     * the carrier-specific status identifier is present. A parcel filled in manually via CMS
     * may have a transport/tracking number an admin typed in, but still lack the identifier
     * a given carrier's status API actually requires (e.g. DPD needs a numeric shipmentId
     * returned by createParcel(), not just a human-readable tracking number).
     */
    public function supportsStatusPolling(Purchase $purchase): bool;
}