<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\InformationBlockType;
use Greendot\EshopBundle\Repository\Project\InformationBlockTypeRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<InformationBlockType>
 *
 * @method        InformationBlockType|Proxy create(array|callable $attributes = [])
 * @method static InformationBlockType|Proxy createOne(array $attributes = [])
 * @method static InformationBlockType|Proxy find(object|array|mixed $criteria)
 * @method static InformationBlockType|Proxy findOrCreate(array $attributes)
 * @method static InformationBlockType|Proxy first(string $sortedField = 'id')
 * @method static InformationBlockType|Proxy last(string $sortedField = 'id')
 * @method static InformationBlockType|Proxy random(array $attributes = [])
 * @method static InformationBlockType|Proxy randomOrCreate(array $attributes = [])
 * @method static InformationBlockTypeRepository|RepositoryProxy repository()
 * @method static InformationBlockType[]|Proxy[] all()
 * @method static InformationBlockType[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static InformationBlockType[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static InformationBlockType[]|Proxy[] findBy(array $attributes)
 * @method static InformationBlockType[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static InformationBlockType[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<InformationBlockType> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<InformationBlockType> createOne(array $attributes = [])
 * @phpstan-method static Proxy<InformationBlockType> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<InformationBlockType> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<InformationBlockType> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<InformationBlockType> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<InformationBlockType> random(array $attributes = [])
 * @phpstan-method static Proxy<InformationBlockType> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<InformationBlockType> repository()
 * @phpstan-method static list<Proxy<InformationBlockType>> all()
// * @phpstan-method static list<Proxy<InformationBlockType>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<InformationBlockType>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<InformationBlockType>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<InformationBlockType>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<InformationBlockType>> randomSet(int $number, array $attributes = [])
 */
final class InformationBlockTypeFactory extends ModelFactory
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
            'name' => ucwords(self::faker()->words(3, true)),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(InformationBlockType $informationBlockType): void {})
        ;
    }

    protected static function getClass(): string
    {
        return InformationBlockType::class;
    }
}
