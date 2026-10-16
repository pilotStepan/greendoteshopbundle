<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the locale a given purchase should be communicated in
 * (order emails/SMS, generated documents), independent of the current
 * request/session locale.
 *
 * Preference order: the client's own locale, then the currency snapshotted
 * on the purchase (see CurrencyManager::getForPurchase()), then the
 * currency on the purchase's PaymentType (for purchases predating the
 * Purchase::$currency column), then the app default.
 *
 * @see CurrencyManager::getForPurchase()
 */
final readonly class PurchaseLocaleResolver
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private string $defaultLocale,
    ) {}

    public function resolve(Purchase $purchase): string
    {
        return $this->nonEmpty($purchase->getClient()?->getLocale())
            ?? $this->nonEmpty($purchase->getCurrency()?->getDefaultLocale())
            ?? $this->nonEmpty($purchase->getPaymentType()?->getCurrency()?->getDefaultLocale())
            ?? $this->defaultLocale;
    }

    private function nonEmpty(?string $locale): ?string
    {
        return $locale === null || $locale === '' ? null : $locale;
    }
}
