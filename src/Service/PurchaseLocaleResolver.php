<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the locale a given purchase should be communicated in
 * (order emails/SMS, generated documents), independent of the current
 * request/session locale.
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
        return $purchase->getClient()?->getLocale()
            ?? $purchase->getPaymentType()?->getCurrency()?->getDefaultLocale()
            ?? $this->defaultLocale;
    }
}
