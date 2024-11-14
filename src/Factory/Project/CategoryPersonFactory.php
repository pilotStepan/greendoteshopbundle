<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\CategoryPerson;
use Greendot\EshopBundle\Repository\Project\CategoryPersonRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CategoryPerson>
 *
 * @method        CategoryPerson|Proxy create(array|callable $attributes = [])
 * @method static CategoryPerson|Proxy createOne(array $attributes = [])
 * @method static CategoryPerson|Proxy find(object|array|mixed $criteria)
 * @method static CategoryPerson|Proxy findOrCreate(array $attributes)
 * @method static CategoryPerson|Proxy first(string $sortedField = 'id')
 * @method static CategoryPerson|Proxy last(string $sortedField = 'id')
 * @method static CategoryPerson|Proxy random(array $attributes = [])
 * @method static CategoryPerson|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryPersonRepository|RepositoryProxy repository()
 * @method static CategoryPerson[]|Proxy[] all()
 * @method static CategoryPerson[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryPerson[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryPerson[]|Proxy[] findBy(array $attributes)
 * @method static CategoryPerson[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryPerson[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CategoryPerson> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CategoryPerson> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CategoryPerson> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CategoryPerson> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CategoryPerson> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryPerson> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryPerson> random(array $attributes = [])
 * @phpstan-method static Proxy<CategoryPerson> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CategoryPerson> repository()
 * @phpstan-method static list<Proxy<CategoryPerson>> all()
// * @phpstan-method static list<Proxy<CategoryPerson>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<CategoryPerson>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<CategoryPerson>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<CategoryPerson>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<CategoryPerson>> randomSet(int $number, array $attributes = [])
 */
final class CategoryPersonFactory extends ModelFactory
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
            // ->afterInstantiate(function(CategoryPerson $categoryPerson): void {})
        ;
    }

    protected static function getClass(): string
    {
        return CategoryPerson::class;
    }
}
