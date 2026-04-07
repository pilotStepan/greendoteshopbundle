<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\DependencyInjection\Compiler;

use ReflectionClass;
use Greendot\EshopBundle\Attribute\AsPurchaseNotification;
use Greendot\EshopBundle\Notification\PurchaseNotificationDispatcher;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class PurchaseNotificationHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(PurchaseNotificationDispatcher::class)) {
            return;
        }

        $handlers = [];

        foreach ($container->findTaggedServiceIds('greendot_eshop.purchase_notification') as $serviceId => $_tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if (!$class || !class_exists($class)) {
                continue;
            }

            $attributes = (new ReflectionClass($class))->getAttributes(AsPurchaseNotification::class);

            if (empty($attributes)) {
                continue;
            }

            $alias = $attributes[0]->newInstance()->alias;
            $handlers[$alias] = new Reference($serviceId);
        }

        $container->getDefinition(PurchaseNotificationDispatcher::class)
            ->setArgument('$handlers', $handlers);
    }
}
