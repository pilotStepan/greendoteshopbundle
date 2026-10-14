<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Translation\LocaleSwitcher;
use Greendot\EshopBundle\Doctrine\DoctrineFiltersConfigNames;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class PurchaseTransitionNotificationHandler
{
    public function __construct(
        private PurchaseRepository     $purchaseRepository,
        #[AutowireLocator('greendot_eshop.purchase_notification')]
        private ContainerInterface     $locator,
        private LoggerInterface        $logger,
        private PurchaseLocaleResolver $purchaseLocaleResolver,
        private LocaleSwitcher         $localeSwitcher,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(PurchaseTransitionNotification $msg): void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);

        if (!$purchase) {
            throw new UnrecoverableMessageHandlingException('Purchase not found for ID: ' . $msg->purchaseId);
        }

        if (!$this->locator->has($msg->alias)) {
            $this->logger->warning('No purchase notification handler found for alias', [
                'alias' => $msg->alias,
                'transition' => $msg->transition,
                'purchase' => $purchase->getId(),
            ]);
            return;
        }

        $purchaseNotificationHandler = $this->locator->get($msg->alias);

        if (!$purchaseNotificationHandler instanceof PurchaseNotificationHandlerInterface) {
            throw new UnrecoverableMessageHandlingException(sprintf(
                'Service for alias "%s" must implement PurchaseNotificationHandlerInterface.',
                $msg->alias,
            ));
        }

        $locale = $this->purchaseLocaleResolver->resolve($purchase);

        $disabledFilters = $this->disableActiveFilters();

        try {
            $this->localeSwitcher->runWithLocale(
                $locale,
                fn () => $purchaseNotificationHandler->handle($purchase, $msg->transition),
            );
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
