<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ProductUploadGroup;
use Greendot\EshopBundle\Repository\Project\ProductUploadGroupRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ProductUploadGroup>
 *
 * @method        ProductUploadGroup|Proxy create(array|callable $attributes = [])
 * @method static ProductUploadGroup|Proxy createOne(array $attributes = [])
 * @method static ProductUploadGroup|Proxy find(object|array|mixed $criteria)
 * @method static ProductUploadGroup|Proxy findOrCreate(array $attributes)
 * @method static ProductUploadGroup|Proxy first(string $sortedField = 'id')
 * @method static ProductUploadGroup|Proxy last(string $sortedField = 'id')
 * @method static ProductUploadGroup|Proxy random(array $attributes = [])
 * @method static ProductUploadGroup|Proxy randomOrCreate(array $attributes = [])
 * @method static ProductUploadGroupRepository|RepositoryProxy repository()
 * @method static ProductUploadGroup[]|Proxy[] all()
 * @method static ProductUploadGroup[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProductUploadGroup[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ProductUploadGroup[]|Proxy[] findBy(array $attributes)
 * @method static ProductUploadGroup[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ProductUploadGroup[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ProductUploadGroup> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ProductUploadGroup> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ProductUploadGroup> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ProductUploadGroup> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ProductUploadGroup> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductUploadGroup> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductUploadGroup> random(array $attributes = [])
 * @phpstan-method static Proxy<ProductUploadGroup> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ProductUploadGroup> repository()
 * @phpstan-method static list<Proxy<ProductUploadGroup>> all()
// * @phpstan-method static list<Proxy<ProductUploadGroup>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<ProductUploadGroup>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<ProductUploadGroup>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<ProductUploadGroup>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<ProductUploadGroup>> randomSet(int $number, array $attributes = [])
 */
final class ProductUploadGroupFactory extends ModelFactory
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

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        return [
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ProductUploadGroup $productUploadGroup): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ProductUploadGroup::class;
    }
}
