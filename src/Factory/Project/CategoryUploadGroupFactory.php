<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\CategoryUploadGroup;
use Greendot\EshopBundle\Repository\Project\CategoryUploadGroupRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CategoryUploadGroup>
 *
 * @method        CategoryUploadGroup|Proxy create(array|callable $attributes = [])
 * @method static CategoryUploadGroup|Proxy createOne(array $attributes = [])
 * @method static CategoryUploadGroup|Proxy find(object|array|mixed $criteria)
 * @method static CategoryUploadGroup|Proxy findOrCreate(array $attributes)
 * @method static CategoryUploadGroup|Proxy first(string $sortedField = 'id')
 * @method static CategoryUploadGroup|Proxy last(string $sortedField = 'id')
 * @method static CategoryUploadGroup|Proxy random(array $attributes = [])
 * @method static CategoryUploadGroup|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryUploadGroupRepository|RepositoryProxy repository()
 * @method static CategoryUploadGroup[]|Proxy[] all()
 * @method static CategoryUploadGroup[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryUploadGroup[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryUploadGroup[]|Proxy[] findBy(array $attributes)
 * @method static CategoryUploadGroup[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryUploadGroup[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CategoryUploadGroup> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CategoryUploadGroup> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CategoryUploadGroup> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CategoryUploadGroup> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CategoryUploadGroup> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryUploadGroup> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryUploadGroup> random(array $attributes = [])
 * @phpstan-method static Proxy<CategoryUploadGroup> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CategoryUploadGroup> repository()
 * @phpstan-method static list<Proxy<CategoryUploadGroup>> all()
// * @phpstan-method static list<Proxy<CategoryUploadGroup>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<CategoryUploadGroup>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<CategoryUploadGroup>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<CategoryUploadGroup>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<CategoryUploadGroup>> randomSet(int $number, array $attributes = [])
 */
final class CategoryUploadGroupFactory extends ModelFactory
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
            // ->afterInstantiate(function(CategoryUploadGroup $categoryUploadGroup): void {})
        ;
    }

    protected static function getClass(): string
    {
        return CategoryUploadGroup::class;
    }
}
