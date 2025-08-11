<?php

namespace Greendot\EshopBundle\Enum;

enum TransportationAPI : string
{
    case CP_DO_RUKY = "cp_do_ruky";         // Česká Pošta do Ruky (API cpost, jiná služba v api)
    case CP_BALIKOVNA = "cp_balikovna";     // Česká pošta balíkovna (API cpost, jiná služba v api)
    case DPD = "dpd";                       // DPD
    case PACKETA = "packeta";               // Zásilkovna
}