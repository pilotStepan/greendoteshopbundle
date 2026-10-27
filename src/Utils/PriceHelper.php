<?php

namespace Greendot\EshopBundle\Utils;

use Greendot\EshopBundle\Entity\Project\Currency;

class PriceHelper
{
    /**
     * @param bool $trimWholeDecimals print whole amounts without decimals (32 € instead of 32,0 €)
     */
    public static function formatPrice(float $price, Currency $currency, bool $showFree = true, string $freeLabel = 'Zdarma', bool $trimWholeDecimals = false): string
    {
        if ($showFree && $price == 0) {
            return $freeLabel;
        }
        $formattedPrice = number_format(
            $price,
            $trimWholeDecimals && $price == round($price) ? 0 : $currency->getRounding(),
            decimal_separator: ',',
            thousands_separator: ' '
        );

        return $currency->isSymbolLeft()
            ? sprintf("%s\u{00A0}%s", $currency->getSymbol(), $formattedPrice)
            : sprintf("%s\u{00A0}%s", $formattedPrice, $currency->getSymbol());
    }
}