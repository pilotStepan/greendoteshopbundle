<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Product>
 *
 * @method        Product|Proxy create(array|callable $attributes = [])
 * @method static Product|Proxy createOne(array $attributes = [])
 * @method static Product|Proxy find(object|array|mixed $criteria)
 * @method static Product|Proxy findOrCreate(array $attributes)
 * @method static Product|Proxy first(string $sortedField = 'id')
 * @method static Product|Proxy last(string $sortedField = 'id')
 * @method static Product|Proxy random(array $attributes = [])
 * @method static Product|Proxy randomOrCreate(array $attributes = [])
 * @method static ProductRepository|RepositoryProxy repository()
 * @method static Product[]|Proxy[] all()
 * @method static Product[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Product[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Product[]|Proxy[] findBy(array $attributes)
 * @method static Product[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Product[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Product> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Product> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Product> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Product> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Product> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Product> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Product> random(array $attributes = [])
 * @phpstan-method static Proxy<Product> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Product> repository()
 * @phpstan-method static list<Proxy<Product>> all()
// * // * @phpstan-method static list<Proxy<Product>> createMany(int $number, array|callable $attributes = [])
// * // * @phpstan-method static list<Proxy<Product>> createSequence(iterable|callable $sequence)
// * // * @phpstan-method static list<Proxy<Product>> findBy(array $attributes)
// * // * @phpstan-method static list<Proxy<Product>> randomRange(int $min, int $max, array $attributes = [])
// * // * @phpstan-method static list<Proxy<Product>> randomSet(int $number, array $attributes = [])
 */
final class ProductFactory extends ModelFactory
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
        $productName = strtoupper(self::faker()->word());
        return [
            'name' => $productName,
            'menu_name' => $productName,
            'description' => $productName . " " . self::faker()->text(100),
            'title' => $productName,
            'slug' => str_replace(' ', '-', strtolower($productName)) . '-id-' . self::faker()->uuid(),
            'isActive' => 1,
            'sequence' => 1,
            'javascript' => $productName . self::faker()->text(100),
            'is_indexable' => 1,
            'state' => 'draft',
            'producer' => ProducerFactory::random()->object(),
            'upload' => UploadFactory::random()->object(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
//    protected function initialize(): self
//    {
//        return $this->afterInstantiate(function (Product $product): void {
//
//        });
//    }

    protected static function getClass(): string
    {
        return Product::class;
    }
}
