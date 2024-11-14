<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ProductVariantUploadGroup;
use Greendot\EshopBundle\Repository\Project\ProductVariantUploadGroupRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ProductVariantUploadGroup>
 *
 * @method        ProductVariantUploadGroup|Proxy create(array|callable $attributes = [])
 * @method static ProductVariantUploadGroup|Proxy createOne(array $attributes = [])
 * @method static ProductVariantUploadGroup|Proxy find(object|array|mixed $criteria)
 * @method static ProductVariantUploadGroup|Proxy findOrCreate(array $attributes)
 * @method static ProductVariantUploadGroup|Proxy first(string $sortedField = 'id')
 * @method static ProductVariantUploadGroup|Proxy last(string $sortedField = 'id')
 * @method static ProductVariantUploadGroup|Proxy random(array $attributes = [])
 * @method static ProductVariantUploadGroup|Proxy randomOrCreate(array $attributes = [])
 * @method static ProductVariantUploadGroupRepository|RepositoryProxy repository()
 * @method static ProductVariantUploadGroup[]|Proxy[] all()
 * @method static ProductVariantUploadGroup[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProductVariantUploadGroup[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ProductVariantUploadGroup[]|Proxy[] findBy(array $attributes)
 * @method static ProductVariantUploadGroup[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ProductVariantUploadGroup[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ProductVariantUploadGroup> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ProductVariantUploadGroup> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ProductVariantUploadGroup> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ProductVariantUploadGroup> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ProductVariantUploadGroup> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductVariantUploadGroup> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductVariantUploadGroup> random(array $attributes = [])
 * @phpstan-method static Proxy<ProductVariantUploadGroup> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ProductVariantUploadGroup> repository()
 * @phpstan-method static list<Proxy<ProductVariantUploadGroup>> all()
// * @phpstan-method static list<Proxy<ProductVariantUploadGroup>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<ProductVariantUploadGroup>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<ProductVariantUploadGroup>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<ProductVariantUploadGroup>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<ProductVariantUploadGroup>> randomSet(int $number, array $attributes = [])
 */
final class ProductVariantUploadGroupFactory extends ModelFactory
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
            // ->afterInstantiate(function(ProductVariantUploadGroup $productVariantUploadGroup): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ProductVariantUploadGroup::class;
    }
}
