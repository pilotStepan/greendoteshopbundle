<?php

namespace Greendot\EshopBundle\Utils;

use Greendot\EshopBundle\Entity\Project\Currency;

class PriceHelper
{
    public static function formatPrice(float $price, Currency $currency, bool $showFree = true, string $freeLabel = 'Zdarma'): string
    {
        if ($showFree && $price == 0) return $freeLabel;
        $formattedPrice = number_format(
            $price,
            $currency->getRounding(),
            decimal_separator: ',',
            thousands_separator: ' '
        );

        return $currency->isSymbolLeft()
            ? sprintf("%s\u{00A0}%s", $currency->getSymbol(), $formattedPrice)
            : sprintf("%s\u{00A0}%s", $formattedPrice, $currency->getSymbol());
    }
}