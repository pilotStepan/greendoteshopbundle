<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Client;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Client>
 */
final class ClientFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Client::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->firstName(),
            'surname' => self::faker()->lastName(),
            'mail' => self::faker()->unique()->safeEmail(),
            'password' => 'not-a-real-hash',
        ];
    }
}
