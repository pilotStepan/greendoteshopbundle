<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Repository\Project\ClientAddressRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ClientAddress>
 *
 * @method        ClientAddress|Proxy                              create(array|callable $attributes = [])
 * @method static ClientAddress|Proxy                              createOne(array $attributes = [])
 * @method static ClientAddress|Proxy                              find(object|array|mixed $criteria)
 * @method static ClientAddress|Proxy                              findOrCreate(array $attributes)
 * @method static ClientAddress|Proxy                              first(string $sortedField = 'id')
 * @method static ClientAddress|Proxy                              last(string $sortedField = 'id')
 * @method static ClientAddress|Proxy                              random(array $attributes = [])
 * @method static ClientAddress|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ClientAddressRepository|ProxyRepositoryDecorator repository()
 * @method static ClientAddress[]|Proxy[]                          all()
 * @method static ClientAddress[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static ClientAddress[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static ClientAddress[]|Proxy[]                          findBy(array $attributes)
 * @method static ClientAddress[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static ClientAddress[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ClientAddressFactory extends PersistentProxyObjectFactory
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
        return ClientAddress::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'street' => self::faker()->streetAddress(),
            'city' => self::faker()->city(),
            'zip' => self::faker()->postcode(),
            'country' => 'CZ',
            'company' => self::faker()->company(),
            'ic' => self::faker()->numerify('###'),
            'dic' => self::faker()->numerify('###'),
            'ship_name' => self::faker()->firstName(),
            'ship_surname' => self::faker()->lastName(),
            'ship_company' => self::faker()->company(),
            'ship_street' => self::faker()->streetAddress(),
            'ship_city' => self::faker()->city(),
            'ship_zip' => self::faker()->postcode(),
            'ship_country' => 'CZ',
            'date_created' => new \DateTime(),
            'is_primary' => true,
            'ship_ic' => self::faker()->numerify('###'),
            'ship_dic' => self::faker()->numerify('###'),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(ClientAddress $clientAddress): void {})
        ;
    }
}
