<?php

namespace Greendot\EshopBundle\Utils;

use Greendot\EshopBundle\Entity\Project\Currency;

class PriceHelper
{
    public static function formatPrice(float $price, Currency $currency, bool $showFree = true): string
    {
        if ($showFree && $price == 0) return 'Zdarma';
        $formattedPrice = number_format($price, $currency->getRounding());

        return $currency->isSymbolLeft()
            ? sprintf('%s %s', $currency->getSymbol(), $formattedPrice)
            : sprintf('%s %s', $formattedPrice, $currency->getSymbol());
    }
}