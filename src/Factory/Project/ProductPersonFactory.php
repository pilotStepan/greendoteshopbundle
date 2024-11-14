<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ProductPerson;
use Greendot\EshopBundle\Repository\Project\ProductPersonRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ProductPerson>
 *
 * @method        ProductPerson|Proxy create(array|callable $attributes = [])
 * @method static ProductPerson|Proxy createOne(array $attributes = [])
 * @method static ProductPerson|Proxy find(object|array|mixed $criteria)
 * @method static ProductPerson|Proxy findOrCreate(array $attributes)
 * @method static ProductPerson|Proxy first(string $sortedField = 'id')
 * @method static ProductPerson|Proxy last(string $sortedField = 'id')
 * @method static ProductPerson|Proxy random(array $attributes = [])
 * @method static ProductPerson|Proxy randomOrCreate(array $attributes = [])
 * @method static ProductPersonRepository|RepositoryProxy repository()
 * @method static ProductPerson[]|Proxy[] all()
 * @method static ProductPerson[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProductPerson[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ProductPerson[]|Proxy[] findBy(array $attributes)
 * @method static ProductPerson[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ProductPerson[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ProductPerson> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ProductPerson> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ProductPerson> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ProductPerson> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ProductPerson> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductPerson> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductPerson> random(array $attributes = [])
 * @phpstan-method static Proxy<ProductPerson> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ProductPerson> repository()
 * @phpstan-method static list<Proxy<ProductPerson>> all()
// * @phpstan-method static list<Proxy<ProductPerson>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<ProductPerson>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<ProductPerson>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<ProductPerson>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<ProductPerson>> randomSet(int $number, array $attributes = [])
 */
final class ProductPersonFactory extends ModelFactory
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
            'sequence' => 1,
            'product' => ProductFactory::random(),
            'person' => PersonFactory::random(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ProductPerson $productPerson): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ProductPerson::class;
    }
}
