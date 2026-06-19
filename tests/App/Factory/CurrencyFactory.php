<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Currency;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Currency>
 */
final class CurrencyFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Currency::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => 'Euro',
            'symbol' => '€',
            'isDefault' => true,
            'rounding' => 2,
            // No getter/setter exists for this property, so the default PropertyAccessor-based
            // instantiator can't set it; force it via reflection instead.
            'defaultLocale' => 'en',
        ];
    }

    protected function initialize(): static
    {
        return parent::initialize()->instantiateWith(
            Instantiator::withConstructor()->alwaysForce('defaultLocale'),
        );
    }
}
