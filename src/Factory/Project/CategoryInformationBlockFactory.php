<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\CategoryInformationBlock;
use Greendot\EshopBundle\Repository\Project\CategoryInformationBlockRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<CategoryInformationBlock>
 *
 * @method        CategoryInformationBlock|Proxy create(array|callable $attributes = [])
 * @method static CategoryInformationBlock|Proxy createOne(array $attributes = [])
 * @method static CategoryInformationBlock|Proxy find(object|array|mixed $criteria)
 * @method static CategoryInformationBlock|Proxy findOrCreate(array $attributes)
 * @method static CategoryInformationBlock|Proxy first(string $sortedField = 'id')
 * @method static CategoryInformationBlock|Proxy last(string $sortedField = 'id')
 * @method static CategoryInformationBlock|Proxy random(array $attributes = [])
 * @method static CategoryInformationBlock|Proxy randomOrCreate(array $attributes = [])
 * @method static CategoryInformationBlockRepository|RepositoryProxy repository()
 * @method static CategoryInformationBlock[]|Proxy[] all()
 * @method static CategoryInformationBlock[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static CategoryInformationBlock[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static CategoryInformationBlock[]|Proxy[] findBy(array $attributes)
 * @method static CategoryInformationBlock[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static CategoryInformationBlock[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<CategoryInformationBlock> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<CategoryInformationBlock> createOne(array $attributes = [])
 * @phpstan-method static Proxy<CategoryInformationBlock> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<CategoryInformationBlock> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<CategoryInformationBlock> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryInformationBlock> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<CategoryInformationBlock> random(array $attributes = [])
 * @phpstan-method static Proxy<CategoryInformationBlock> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<CategoryInformationBlock> repository()
 * @phpstan-method static list<Proxy<CategoryInformationBlock>> all()
// * @phpstan-method static list<Proxy<CategoryInformationBlock>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<CategoryInformationBlock>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<CategoryInformationBlock>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<CategoryInformationBlock>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<CategoryInformationBlock>> randomSet(int $number, array $attributes = [])
 */
final class CategoryInformationBlockFactory extends ModelFactory
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
            'category' => CategoryFactory::new(),
            'informationBlock' => InformationBlockFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(CategoryInformationBlock $categoryInformationBlock): void {})
        ;
    }

    protected static function getClass(): string
    {
        return CategoryInformationBlock::class;
    }
}
