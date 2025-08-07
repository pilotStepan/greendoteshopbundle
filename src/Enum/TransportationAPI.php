<?php

namespace Greendot\EshopBundle\Enum;

enum TransportationAPI : string
{
    case cp_do_ruky = "CP_DO_RUKY";         // Česká Pošta do Ruky (API cpost, jiná služba v api)
    case cp_balikovna = "CP_BALIKOVNA";     // Česká pošta balíkovna (API cpost, jiná služba v api)
    case dpd = "DPD";                       // DPD
    case packeta = "PACKETA";               // Zásilkovna
}