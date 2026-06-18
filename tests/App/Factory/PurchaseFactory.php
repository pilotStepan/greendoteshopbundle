<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Purchase>
 */
final class PurchaseFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Purchase::class;
    }

    protected function defaults(): array|callable
    {
        return [];
    }
}
