<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Greendot\EshopBundle\Repository\Project\SubMenuTypeRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<SubMenuType>
 *
 * @method        SubMenuType|Proxy create(array|callable $attributes = [])
 * @method static SubMenuType|Proxy createOne(array $attributes = [])
 * @method static SubMenuType|Proxy find(object|array|mixed $criteria)
 * @method static SubMenuType|Proxy findOrCreate(array $attributes)
 * @method static SubMenuType|Proxy first(string $sortedField = 'id')
 * @method static SubMenuType|Proxy last(string $sortedField = 'id')
 * @method static SubMenuType|Proxy random(array $attributes = [])
 * @method static SubMenuType|Proxy randomOrCreate(array $attributes = [])
 * @method static SubMenuTypeRepository|RepositoryProxy repository()
 * @method static SubMenuType[]|Proxy[] all()
 * @method static SubMenuType[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static SubMenuType[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static SubMenuType[]|Proxy[] findBy(array $attributes)
 * @method static SubMenuType[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static SubMenuType[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<SubMenuType> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<SubMenuType> createOne(array $attributes = [])
 * @phpstan-method static Proxy<SubMenuType> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<SubMenuType> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<SubMenuType> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<SubMenuType> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<SubMenuType> random(array $attributes = [])
 * @phpstan-method static Proxy<SubMenuType> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<SubMenuType> repository()
 * @phpstan-method static list<Proxy<SubMenuType>> all()
// * @phpstan-method static list<Proxy<SubMenuType>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<SubMenuType>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<SubMenuType>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<SubMenuType>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<SubMenuType>> randomSet(int $number, array $attributes = [])
 */
final class SubMenuTypeFactory extends ModelFactory
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
            // ->afterInstantiate(function(SubMenuType $subMenuType): void {})
        ;
    }

    protected static function getClass(): string
    {
        return SubMenuType::class;
    }
}
