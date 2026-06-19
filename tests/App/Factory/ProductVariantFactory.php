<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ProductVariant>
 */
final class ProductVariantFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ProductVariant::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->words(2, true),
            'product' => ProductFactory::new(),
        ];
    }
}
