<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Money\Exception;

use Greendot\EshopBundle\Entity\Project\Purchase;
use InvalidArgumentException;

/**
 * Thrown when a Purchase's frozen currency snapshot disagrees with its PaymentType's currency:
 * ManagePurchase::ensureCurrency() (checkout/init_order), QRcodeGenerator::getUri(), and
 * GPWebpay::getPayLink() all refuse to proceed on a mismatch rather than charge or display the
 * wrong account. PurchaseStateSubscriber blocks the checkout/init_order transitions themselves
 * before a mismatched purchase can even reach these call sites.
 */
class PurchaseCurrencyMismatchException extends InvalidArgumentException
{
    public static function forPurchase(Purchase $purchase): self
    {
        return new self(sprintf(
            'Purchase #%s currency (%s) does not match its payment type currency (%s); refusing to proceed.',
            $purchase->getId() ?? '?',
            $purchase->getCurrency()?->getIso() ?? '?',
            $purchase->getPaymentType()?->getCurrency()?->getIso() ?? '?',
        ));
    }
}
