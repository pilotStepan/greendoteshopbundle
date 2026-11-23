<?php

namespace Greendot\EshopBundle\Parcel;

enum TransportationAPI: string
{
    case CP_DO_RUKY = "cp_do_ruky";           // Česká Pošta do Ruky (API cpost, jiná služba v api)
    case CP_BALIKOVNA = "cp_balikovna";       // Česká pošta balíkovna (API cpost, jiná služba v api)
    case DPD = "dpd";                         // DPD
    case PACKETA = "packeta";                 // Zásilkovna
    case PACKETA_ADDRESS = "packeta_address"; // Packeta home/address delivery

    /**
     * Carrier name for admin-facing text (Czech).
     */
    public function carrierName(): string
    {
        return match ($this) {
            self::CP_DO_RUKY => 'Česká pošta (do ruky)',
            self::CP_BALIKOVNA => 'Česká pošta (Balíkovna)',
            self::DPD => 'DPD',
            self::PACKETA => 'Zásilkovna',
            self::PACKETA_ADDRESS => 'Zásilkovna (na adresu)',
        };
    }
}
