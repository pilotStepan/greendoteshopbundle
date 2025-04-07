<?php

namespace Greendot\EshopBundle\Service\Parcel;

use LogicException;

class ParcelServiceRegistry
{
    /* @var array<int, ParcelServiceInterface> */
    private array $services;

    public function __construct(
        CzechPostParcel $czechPostParcel,
        PacketeryParcel $packeteryParcel
    ) {
        $this->services = [
            3 => $packeteryParcel,
            4 => $czechPostParcel,
        ];
    }

    public function get(int $transportationId): ParcelServiceInterface
    {
        if (!$this->supports($transportationId)) {
            throw new LogicException("No parcel service supports transportation ID $transportationId");
        }
        return $this->services[$transportationId];
    }

    private function supports(int $transportationId): bool
    {
        return isset($this->services[$transportationId]);
    }
}
