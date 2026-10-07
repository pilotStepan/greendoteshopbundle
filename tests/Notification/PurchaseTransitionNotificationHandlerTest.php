<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Notification;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Container\ContainerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Doctrine\DoctrineFiltersConfigNames;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\PurchaseLocaleResolver;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Notification\PurchaseTransitionNotification;
use Greendot\EshopBundle\Notification\PurchaseTransitionNotificationHandler;

/**
 * Regression coverage: the notification must actually be rendered under the purchase's
 * locale (previously only a receive-side Messenger middleware switched it, which is a
 * no-op on the bundle's default synchronous bus - see PurchaseTransitionNotificationHandler),
 * and the locale switch must be scoped to the callback, not leaked afterwards.
 *
 * CurrencyManager no longer implements LocaleAwareInterface (see CurrencyLocaleListener),
 * so it is structurally impossible for this locale switch to affect session currency
 * anymore - no test is needed for that case.
 */
class PurchaseTransitionNotificationHandlerTest extends TestCase
{
    public function testDelegatesUnderThePurchasesResolvedLocaleAndRestoresLocaleAfter(): void
    {
        $localeSpy = new class implements LocaleAwareInterface {
            public array $seenLocales = [];
            private string $locale = 'cs';

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
                $this->seenLocales[] = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };

        $localeSwitcher = new LocaleSwitcher('cs', [$localeSpy]);

        $localeDuringHandling = null;
        $notificationHandler = $this->buildNotificationHandler(function () use (&$localeDuringHandling, $localeSwitcher) {
            $localeDuringHandling = $localeSwitcher->getLocale();
        });

        $handler = $this->buildHandler($notificationHandler, $localeSwitcher);

        $purchase = new Purchase();
        $purchase->setClient((new Client())->setLocale('sk'));
        $this->setPurchaseId($purchase, 1);

        $handler(new PurchaseTransitionNotification(purchaseId: 1, transition: 'paid', alias: 'customer_email'));

        $this->assertSame('sk', $localeDuringHandling, 'notification must be rendered under the purchase locale');
        $this->assertSame('cs', $localeSwitcher->getLocale(), 'locale must be restored after the notification is handled');
    }

    /**
     * Regression coverage: a purchase containing a deactivated product failed with
     * "Entity of type Product for IDs id(..) was not found", because the active filters
     * hid the product when its lazy proxy was initialized while rendering the notification.
     */
    public function testActiveFiltersAreDisabledWhileHandlingAndRestoredAfter(): void
    {
        $enabledDuringHandling = null;
        $notificationHandler = $this->buildNotificationHandler(function () use (&$enabledDuringHandling) {
            $enabledDuringHandling = $this->enabledFilters;
        });

        $handler = $this->buildHandler($notificationHandler, new LocaleSwitcher('cs', []));
        $this->setPurchaseId(new Purchase(), 1);

        $handler(new PurchaseTransitionNotification(purchaseId: 1, transition: 'paid', alias: 'customer_email'));

        $this->assertSame([], $enabledDuringHandling, 'active filters must be off while the notification is rendered');
        $this->assertSame($this->activeFilters(), $this->enabledFilters, 'active filters must be restored afterwards');
    }

    public function testActiveFiltersAreRestoredWhenHandlingFails(): void
    {
        $notificationHandler = $this->buildNotificationHandler(fn () => throw new \RuntimeException('boom'));

        $handler = $this->buildHandler($notificationHandler, new LocaleSwitcher('cs', []));
        $this->setPurchaseId(new Purchase(), 1);

        try {
            $handler(new PurchaseTransitionNotification(purchaseId: 1, transition: 'paid', alias: 'customer_email'));
            $this->fail('exception expected');
        } catch (\RuntimeException) {
        }

        $this->assertSame($this->activeFilters(), $this->enabledFilters);
    }

    public function testFilterThatWasAlreadyDisabledStaysDisabled(): void
    {
        $this->enabledFilters = [DoctrineFiltersConfigNames::ProductVariantActiveFilter->value];

        $handler = $this->buildHandler($this->buildNotificationHandler(fn () => null), new LocaleSwitcher('cs', []));
        $this->setPurchaseId(new Purchase(), 1);

        $handler(new PurchaseTransitionNotification(purchaseId: 1, transition: 'paid', alias: 'customer_email'));

        $this->assertSame([DoctrineFiltersConfigNames::ProductVariantActiveFilter->value], $this->enabledFilters);
    }

    private function buildNotificationHandler(callable $onHandle): PurchaseNotificationHandlerInterface
    {
        return new class($onHandle) implements PurchaseNotificationHandlerInterface {
            public function __construct(private $onHandle) {}

            public function handle(Purchase $purchase, string $transition): void
            {
                ($this->onHandle)();
            }
        };
    }

    private function buildHandler(
        PurchaseNotificationHandlerInterface $notificationHandler,
        LocaleSwitcher $localeSwitcher,
    ): PurchaseTransitionNotificationHandler {
        $purchaseRepository = $this->createMock(PurchaseRepository::class);
        $purchaseRepository->method('find')->willReturnCallback(function () {
            return $this->currentPurchase;
        });

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($notificationHandler);

        return new PurchaseTransitionNotificationHandler(
            $purchaseRepository,
            $locator,
            new NullLogger(),
            new PurchaseLocaleResolver('cs'),
            $localeSwitcher,
            $this->buildEntityManager(),
        );
    }

    private function buildEntityManager(): EntityManagerInterface
    {
        $this->enabledFilters ??= $this->activeFilters();

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturnCallback(
            fn (string $name) => in_array($name, $this->enabledFilters, true),
        );
        $filters->method('disable')->willReturnCallback(function (string $name) {
            $this->enabledFilters = array_values(array_diff($this->enabledFilters, [$name]));

            return $this->createStub(\Doctrine\ORM\Query\Filter\SQLFilter::class);
        });
        $filters->method('enable')->willReturnCallback(function (string $name) {
            $this->enabledFilters[] = $name;

            return $this->createStub(\Doctrine\ORM\Query\Filter\SQLFilter::class);
        });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getFilters')->willReturn($filters);

        return $entityManager;
    }

    /**
     * @return string[]
     */
    private function activeFilters(): array
    {
        return [
            DoctrineFiltersConfigNames::ProductActiveFilter->value,
            DoctrineFiltersConfigNames::ProductVariantActiveFilter->value,
        ];
    }

    /** @var string[]|null */
    private ?array $enabledFilters = null;

    private ?Purchase $currentPurchase = null;

    private function setPurchaseId(Purchase $purchase, int $id): void
    {
        $reflection = new \ReflectionProperty(Purchase::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($purchase, $id);

        $this->currentPurchase = $purchase;
    }
}
