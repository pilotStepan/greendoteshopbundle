<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Client;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;


final class ClientFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Client::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->firstName(),
            'surname' => self::faker()->lastName(),
            'phone' => rand(1000000, 9999999),
            'mail' => self::faker()->email(),
            'isVerified' => 0,
            'isAnonymous' => 0,
            'agreeNewsletter' => 0,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
//        return $this->afterInstantiate(function(Client $client): void {
//            ClientAddressFactory::new()
//                ->create(['client' => $client]);
//        });
    }
}
