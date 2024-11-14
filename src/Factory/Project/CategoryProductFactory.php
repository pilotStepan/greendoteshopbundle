<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\Repository\Project\CategoryProductRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CategoryProduct>
 *
 * @method        CategoryProduct|Proxy create(array|callable $attributes = [])
 * @method static CategoryProduct|Proxy createOne(array $attributes = [])
 * @method static CategoryProduct|Proxy find(object|array|mixed $criteria)
 * @method static CategoryProduct|Proxy findOrCreate(array $attributes)
 * @method static CategoryProduct|Proxy first(string $sortedField = 'id')
 * @method static CategoryProduct|Proxy last(string $sortedField = 'id')
 * @method static CategoryProduct|Proxy random(array $attributes = [])
 * @method static CategoryProduct|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryProductRepository|RepositoryProxy repository()
 * @method static CategoryProduct[]|Proxy[] all()
 * @method static CategoryProduct[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryProduct[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryProduct[]|Proxy[] findBy(array $attributes)
 * @method static CategoryProduct[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryProduct[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CategoryProduct> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CategoryProduct> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CategoryProduct> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CategoryProduct> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CategoryProduct> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryProduct> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryProduct> random(array $attributes = [])
 * @phpstan-method static Proxy<CategoryProduct> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CategoryProduct> repository()
 * @phpstan-method static list<Proxy<CategoryProduct>> all()
// * @phpstan-method static list<Proxy<CategoryProduct>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<CategoryProduct>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<CategoryProduct>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<CategoryProduct>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<CategoryProduct>> randomSet(int $number, array $attributes = [])
 */
final class CategoryProductFactory extends ModelFactory
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
            'category' => CategoryFactory::random(),
            'product' => ProductFactory::random(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(CategoryProduct $categoryProduct): void {})
        ;
    }

    protected static function getClass(): string
    {
        return CategoryProduct::class;
    }
}
