<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ParameterGroupFilterType;
use Greendot\EshopBundle\Repository\Project\ParameterGroupFilterTypeRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ParameterGroupFilterType>
 *
 * @method        ParameterGroupFilterType|Proxy                              create(array|callable $attributes = [])
 * @method static ParameterGroupFilterType|Proxy                              createOne(array $attributes = [])
 * @method static ParameterGroupFilterType|Proxy                              find(object|array|mixed $criteria)
 * @method static ParameterGroupFilterType|Proxy                              findOrCreate(array $attributes)
 * @method static ParameterGroupFilterType|Proxy                              first(string $sortedField = 'id')
 * @method static ParameterGroupFilterType|Proxy                              last(string $sortedField = 'id')
 * @method static ParameterGroupFilterType|Proxy                              random(array $attributes = [])
 * @method static ParameterGroupFilterType|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ParameterGroupFilterTypeRepository|ProxyRepositoryDecorator repository()
 * @method static ParameterGroupFilterType[]|Proxy[]                          all()
 * @method static ParameterGroupFilterType[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static ParameterGroupFilterType[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static ParameterGroupFilterType[]|Proxy[]                          findBy(array $attributes)
 * @method static ParameterGroupFilterType[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static ParameterGroupFilterType[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ParameterGroupFilterTypeFactory extends PersistentProxyObjectFactory
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
        return ParameterGroupFilterType::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(ParameterGroupFilterType $parameterGroupFilterType): void {})
        ;
    }
}
