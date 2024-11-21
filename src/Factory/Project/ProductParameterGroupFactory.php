<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Repository\Project\ProductParameterGroupRepository;
use Greendot\EshopBundle\Entity\Project\ProductParameterGroup;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ProductParameterGroup>
 *
 * @method        ProductParameterGroup|Proxy                              create(array|callable $attributes = [])
 * @method static ProductParameterGroup|Proxy                              createOne(array $attributes = [])
 * @method static ProductParameterGroup|Proxy                              find(object|array|mixed $criteria)
 * @method static ProductParameterGroup|Proxy                              findOrCreate(array $attributes)
 * @method static ProductParameterGroup|Proxy                              first(string $sortedField = 'id')
 * @method static ProductParameterGroup|Proxy                              last(string $sortedField = 'id')
 * @method static ProductParameterGroup|Proxy                              random(array $attributes = [])
 * @method static ProductParameterGroup|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ProductParameterGroupRepository|ProxyRepositoryDecorator repository()
 * @method static ProductParameterGroup[]|Proxy[]                          all()
 * @method static ProductParameterGroup[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static ProductParameterGroup[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static ProductParameterGroup[]|Proxy[]                          findBy(array $attributes)
 * @method static ProductParameterGroup[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static ProductParameterGroup[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ProductParameterGroupFactory extends PersistentProxyObjectFactory
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
        return ProductParameterGroup::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'isVariant' => self::faker()->boolean(),
            'parameterGroup' => ParameterGroupFactory::new(),
            'product' => ProductFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(ProductParameterGroup $productParameterGroup): void {})
        ;
    }
}
