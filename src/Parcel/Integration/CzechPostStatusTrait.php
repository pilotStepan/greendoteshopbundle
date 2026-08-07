<?php

namespace Greendot\EshopBundle\Parcel\Integration;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Parcel\ParcelDeliveryStateEnum;

/**
 *   - '11' ZPRACOVANÁ DATA, '13' PŘEDANÁ DATA                    -> RECEIVED_DATA
 *   - '21' PODÁNO, '53'/'56'/'5E'/'85'/'SP'/'SR'/'SS' V PŘEPRAVĚ -> IN_TRANSIT
 *   - '5A'/'5I'/'82'/'P2' ULOŽENO(!)                             -> READY_FOR_PICKUP
 *   - '91' DORUČENO                                              -> DELIVERED
 *   - 'P3' VRACÍ SE (returning to sender)                        -> NOT_PICKED_UP
 *   - '51' is ambiguous and depends on reasonID:
 *     - '20' means ULOŽENO (stored at pickup point)
 *     - any other reasonID means V PŘEPRAVĚ
 */
trait CzechPostStatusTrait
{
    public function supportsStatusPolling(Purchase $purchase): bool
    {
        return (bool)$purchase->getTransportNumber();
    }

    private function isPermanentHttpFailure(?int $statusCode): bool
    {
        return $statusCode !== null && $statusCode >= 400 && $statusCode < 500
            && !in_array($statusCode, [408, 429], true);
    }

    private function mapStatusCode(string $statusId, string $reasonId): ParcelDeliveryStateEnum
    {
        $state = match ($statusId) {
            '11', '13'                                     => ParcelDeliveryStateEnum::RECEIVED_DATA,
            '21', '53', '56', '5E', '85', 'SP', 'SR', 'SS' => ParcelDeliveryStateEnum::IN_TRANSIT,
            '5A', '5I', '82', 'P2'                         => ParcelDeliveryStateEnum::READY_FOR_PICKUP,
            '91'                                           => ParcelDeliveryStateEnum::DELIVERED,
            'P3'                                           => ParcelDeliveryStateEnum::NOT_PICKED_UP,
            '51'                                           => $reasonId === '20'
                ? ParcelDeliveryStateEnum::READY_FOR_PICKUP
                : ParcelDeliveryStateEnum::IN_TRANSIT,
            default                                        => null,
        };

        if ($state === null) {
            $this->logger->warning('Unmapped Czech Post parcel status code', ['statusID' => $statusId, 'reasonID' => $reasonId]);
            return ParcelDeliveryStateEnum::UNKNOWN;
        }

        return $state;
    }
}
