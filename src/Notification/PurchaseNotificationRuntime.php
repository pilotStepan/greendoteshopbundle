<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Doctrine\DoctrineFiltersConfigNames;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * The environment purchase notifications are rendered in: the purchase's locale, with the product/variant
 * active filters off (an order may contain products hidden since). Shared by sending and previewing.
 */
final readonly class PurchaseNotificationRuntime
{
    public function __construct(
        private PurchaseLocaleResolver $purchaseLocaleResolver,
        private LocaleSwitcher         $localeSwitcher,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(Purchase $purchase, callable $callback): mixed
    {
        $locale = $this->purchaseLocaleResolver->resolve($purchase);
        $disabledFilters = $this->disableActiveFilters();

        try {
            return $this->localeSwitcher->runWithLocale($locale, $callback);
        } finally {
            foreach ($disabledFilters as $filter) {
                $this->entityManager->getFilters()->enable($filter);
            }
        }
    }

    /**
     * @return string[] names of the filters that were enabled and got disabled
     */
    private function disableActiveFilters(): array
    {
        $filters = $this->entityManager->getFilters();
        $disabled = [];

        foreach ([
            DoctrineFiltersConfigNames::ProductActiveFilter,
            DoctrineFiltersConfigNames::ProductVariantActiveFilter,
        ] as $filter) {
            if ($filters->isEnabled($filter->value)) {
                $filters->disable($filter->value);
                $disabled[] = $filter->value;
            }
        }

        return $disabled;
    }
}
