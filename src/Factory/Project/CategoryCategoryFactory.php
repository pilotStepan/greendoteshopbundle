<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Greendot\EshopBundle\Repository\Project\CategoryCategoryRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CategoryCategory>
 *
 * @method        CategoryCategory|Proxy create(array|callable $attributes = [])
 * @method static CategoryCategory|Proxy createOne(array $attributes = [])
 * @method static CategoryCategory|Proxy find(object|array|mixed $criteria)
 * @method static CategoryCategory|Proxy findOrCreate(array $attributes)
 * @method static CategoryCategory|Proxy first(string $sortedField = 'id')
 * @method static CategoryCategory|Proxy last(string $sortedField = 'id')
 * @method static CategoryCategory|Proxy random(array $attributes = [])
 * @method static CategoryCategory|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryCategoryRepository|RepositoryProxy repository()
 * @method static CategoryCategory[]|Proxy[] all()
 * @method static CategoryCategory[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryCategory[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryCategory[]|Proxy[] findBy(array $attributes)
 * @method static CategoryCategory[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryCategory[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CategoryCategory> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CategoryCategory> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CategoryCategory> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CategoryCategory> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CategoryCategory> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryCategory> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryCategory> random(array $attributes = [])
 * @phpstan-method static Proxy<CategoryCategory> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CategoryCategory> repository()
 * @phpstan-method static list<Proxy<CategoryCategory>> all()
// * // * @phpstan-method static list<Proxy<CategoryCategory>> createMany(int $number, array|callable $attributes = [])
// * // * @phpstan-method static list<Proxy<CategoryCategory>> createSequence(iterable|callable $sequence)
// * // * @phpstan-method static list<Proxy<CategoryCategory>> findBy(array $attributes)
// * // * @phpstan-method static list<Proxy<CategoryCategory>> randomRange(int $min, int $max, array $attributes = [])
// * // * @phpstan-method static list<Proxy<CategoryCategory>> randomSet(int $number, array $attributes = [])
 */
final class CategoryCategoryFactory extends ModelFactory
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
            'category_sub' => CategoryFactory::random(),  // get a random existing Category
            'category_super' => CategoryFactory::random(),
            'is_menu_item' => 0,
            'sequence' => self::faker()->randomNumber(1),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this// ->afterInstantiate(function(CategoryCategory $categoryCategory): void {})
            ;
    }

    protected static function getClass(): string
    {
        return CategoryCategory::class;
    }
}
