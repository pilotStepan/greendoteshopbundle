<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Tests\Workflow\Action;

use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionActionSubscriber;
use Greendot\EshopBundle\Workflow\Action\PurchaseTransitionEffectDescriberInterface;
use Greendot\EshopBundle\Notification\PurchaseNotificationSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Every bundle subscriber that reacts to a specific purchase_flow event in code must describe its effects,
 * or the CMS confirmation would hide them.
 */
class TransitionEffectDescriberCoverageTest extends TestCase
{
    /** Subscribers on the generic event that run configured actions themselves. */
    private const EXEMPT = [
        PurchaseTransitionActionSubscriber::class,
        PurchaseNotificationSubscriber::class,
    ];

    public function testEveryPurchaseFlowSubscriberDescribesItsEffects(): void
    {
        $srcDir = dirname(__DIR__, 3) . '/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));

        $checked = [];
        $missing = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || !str_contains((string) file_get_contents($file->getPathname()), 'EventSubscriberInterface')) {
                continue;
            }

            $class = 'Greendot\\EshopBundle\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($srcDir) + 1));
            if (!class_exists($class) || !is_subclass_of($class, EventSubscriberInterface::class) || in_array($class, self::EXEMPT, true)) {
                continue;
            }

            $events = array_keys($class::getSubscribedEvents());
            if (!array_filter($events, static fn (string $e) => str_starts_with($e, 'workflow.purchase_flow.'))) {
                continue;
            }

            $checked[] = $class;
            if (!is_subclass_of($class, PurchaseTransitionEffectDescriberInterface::class)) {
                $missing[] = $class;
            }
        }

        $this->assertNotEmpty($checked, 'expected to find purchase_flow subscribers');
        $this->assertSame([], $missing, 'implement PurchaseTransitionEffectDescriberInterface');
    }
}
