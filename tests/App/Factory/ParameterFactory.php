<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Parameter;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Parameter>
 */
final class ParameterFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Parameter::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'data' => self::faker()->word(),
        ];
    }
}
