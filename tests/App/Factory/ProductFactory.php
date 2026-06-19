<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Product;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Product>
 */
final class ProductFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->words(3, true),
            'slug' => self::faker()->slug(),
            'isActive' => true,
            'isVisible' => true,
        ];
    }
}
