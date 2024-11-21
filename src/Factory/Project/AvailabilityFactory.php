<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Repository\Project\AvailabilityRepository;
use Greendot\EshopBundle\Entity\Project\Availability;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Availability>
 *
 * @method        Availability|Proxy                              create(array|callable $attributes = [])
 * @method static Availability|Proxy                              createOne(array $attributes = [])
 * @method static Availability|Proxy                              find(object|array|mixed $criteria)
 * @method static Availability|Proxy                              findOrCreate(array $attributes)
 * @method static Availability|Proxy                              first(string $sortedField = 'id')
 * @method static Availability|Proxy                              last(string $sortedField = 'id')
 * @method static Availability|Proxy                              random(array $attributes = [])
 * @method static Availability|Proxy                              randomOrCreate(array $attributes = [])
 * @method static AvailabilityRepository|ProxyRepositoryDecorator repository()
 * @method static Availability[]|Proxy[]                          all()
 * @method static Availability[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Availability[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Availability[]|Proxy[]                          findBy(array $attributes)
 * @method static Availability[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Availability[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class AvailabilityFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return Availability::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'description' => self::faker()->text(),
            'name' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Availability $availability): void {})
        ;
    }
}
