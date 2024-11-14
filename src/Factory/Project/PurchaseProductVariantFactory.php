<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\PurchaseProductVariantRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<PurchaseProductVariant>
 *
 * @method        PurchaseProductVariant|Proxy                              create(array|callable $attributes = [])
 * @method static PurchaseProductVariant|Proxy                              createOne(array $attributes = [])
 * @method static PurchaseProductVariant|Proxy                              find(object|array|mixed $criteria)
 * @method static PurchaseProductVariant|Proxy                              findOrCreate(array $attributes)
 * @method static PurchaseProductVariant|Proxy                              first(string $sortedField = 'id')
 * @method static PurchaseProductVariant|Proxy                              last(string $sortedField = 'id')
 * @method static PurchaseProductVariant|Proxy                              random(array $attributes = [])
 * @method static PurchaseProductVariant|Proxy                              randomOrCreate(array $attributes = [])
 * @method static PurchaseProductVariantRepository|ProxyRepositoryDecorator repository()
 * @method static PurchaseProductVariant[]|Proxy[]                          all()
 * @method static PurchaseProductVariant[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static PurchaseProductVariant[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static PurchaseProductVariant[]|Proxy[]                          findBy(array $attributes)
 * @method static PurchaseProductVariant[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static PurchaseProductVariant[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class PurchaseProductVariantFactory extends PersistentProxyObjectFactory
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
        return PurchaseProductVariant::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'amount' => rand(1,5),
            'state' => 'reserved_for_order',
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(PurchaseProductVariant $purchaseProductVariant): void {})
        ;
    }
}
