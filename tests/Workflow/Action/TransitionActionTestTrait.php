<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseActionInterface;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionActionResolver;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

/**
 * A small purchase_flow: `ship` (pending → shipped) carries the metadata under test, `other` has none.
 */
trait TransitionActionTestTrait
{
    /** @var \ArrayObject<int, string>|null execution log of recordingAction() instances, "<key>:<transition>" */
    private ?\ArrayObject $executionLog = null;

    /**
     * @return list<string>
     */
    private function executed(): array
    {
        return array_values($this->log()->getArrayCopy());
    }

    /**
     * @return \ArrayObject<int, string>
     */
    private function log(): \ArrayObject
    {
        return $this->executionLog ??= new \ArrayObject();
    }

    /**
     * @param array<string, mixed> $shipMetadata
     */
    private function buildWorkflow(array $shipMetadata, ?EventDispatcher $dispatcher = null): Workflow
    {
        $ship = new Transition('ship', 'pending', 'shipped');
        $other = new Transition('other', 'pending', 'shipped');

        $definition = new Definition(
            ['pending', 'shipped'],
            [$ship, $other],
            ['pending'],
            new InMemoryMetadataStore([], [], self::transitionsMetadata($ship, $shipMetadata)),
        );

        return new Workflow($definition, new MethodMarkingStore(false, 'marking'), $dispatcher, 'purchase_flow');
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return \SplObjectStorage<Transition, array<string, mixed>>
     */
    private static function transitionsMetadata(Transition $transition, array $metadata): \SplObjectStorage
    {
        $storage = new \SplObjectStorage();
        $storage->offsetSet($transition, $metadata);

        return $storage;
    }

    /**
     * @param array<string, PurchaseActionInterface>               $actions
     * @param array<string, PurchaseNotificationHandlerInterface>  $notifications
     * @param list<PurchaseTransitionEffectDescriberInterface>     $describers
     */
    private function buildResolver(array $actions = [], array $notifications = [], array $describers = []): PurchaseTransitionActionResolver
    {
        return new PurchaseTransitionActionResolver(
            new ServiceLocator(array_map(static fn ($s) => static fn () => $s, $actions)),
            new ServiceLocator(array_map(static fn ($s) => static fn () => $s, $notifications)),
            $describers,
        );
    }

    private function recordingAction(string $key, ?string $label = null): PurchaseActionInterface
    {
        return new class($key, $label, $this->log()) implements PurchaseActionInterface {
            /** @param \ArrayObject<int, string> $log */
            public function __construct(private string $key, private ?string $label, private \ArrayObject $log) {}

            public function describe(Purchase $purchase, string $transition): ?ActionDescription
            {
                return $this->label === null ? null : new ActionDescription($this->label);
            }

            public function execute(Purchase $purchase, string $transition, array $context): void
            {
                $this->log[] = $this->key . ':' . $transition;
            }
        };
    }

    private function plainNotification(): PurchaseNotificationHandlerInterface
    {
        return new class implements PurchaseNotificationHandlerInterface {
            public function handle(Purchase $purchase, string $transition): void {}
        };
    }

    private function purchase(int $id = 1): Purchase
    {
        $purchase = new Purchase();
        $purchase->setMarking(['pending' => 1]);
        (new \ReflectionProperty(Purchase::class, 'id'))->setValue($purchase, $id);

        return $purchase;
    }

    private function transition(Workflow $workflow, string $name): Transition
    {
        foreach ($workflow->getDefinition()->getTransitions() as $t) {
            if ($t->getName() === $name) {
                return $t;
            }
        }

        throw new \LogicException($name);
    }
}
