<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\CurrencyManager;

/**
 * Ensures every Purchase gets a currency snapshot as soon as it is persisted (cart creation,
 * wishlist, admin-created orders, ...), so later reads never have to fall back to the
 * viewer's session currency for it. A payment type carrying its own currency wins over the
 * session currency, mirroring Purchase::setPaymentType().
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Purchase::class)]
readonly class PurchaseCurrencyListener
{
    public function __construct(private CurrencyManager $currencyManager) {}

    public function prePersist(Purchase $purchase): void
    {
        if ($purchase->getCurrency() !== null) {
            return;
        }

        $currency = $purchase->getPaymentType()?->getCurrency() ?? $this->currencyManager->get();
        $purchase->setCurrency($currency);
    }
}
