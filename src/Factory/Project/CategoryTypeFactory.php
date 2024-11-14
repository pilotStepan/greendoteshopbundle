<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Repository\Project\CategoryTypeRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CategoryType>
 *
 * @method        CategoryType|Proxy create(array|callable $attributes = [])
 * @method static CategoryType|Proxy createOne(array $attributes = [])
 * @method static CategoryType|Proxy find(object|array|mixed $criteria)
 * @method static CategoryType|Proxy findOrCreate(array $attributes)
 * @method static CategoryType|Proxy first(string $sortedField = 'id')
 * @method static CategoryType|Proxy last(string $sortedField = 'id')
 * @method static CategoryType|Proxy random(array $attributes = [])
 * @method static CategoryType|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryTypeRepository|RepositoryProxy repository()
 * @method static CategoryType[]|Proxy[] all()
 * @method static CategoryType[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryType[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryType[]|Proxy[] findBy(array $attributes)
 * @method static CategoryType[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryType[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CategoryType> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CategoryType> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CategoryType> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CategoryType> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CategoryType> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryType> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryType> random(array $attributes = [])
 * @phpstan-method static Proxy<CategoryType> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CategoryType> repository()
 * @phpstan-method static list<Proxy<CategoryType>> all()
// * @phpstan-method static list<Proxy<CategoryType>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<CategoryType>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<CategoryType>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<CategoryType>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<CategoryType>> randomSet(int $number, array $attributes = [])
 */
final class CategoryTypeFactory extends ModelFactory
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
            'controllerName' => self::faker()->text(255),
            'name' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(CategoryType $categoryType): void {})
        ;
    }

    protected static function getClass(): string
    {
        return CategoryType::class;
    }
}
