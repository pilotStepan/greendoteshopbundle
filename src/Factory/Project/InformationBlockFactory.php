<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\DataFixtures\InformationBlockTypeFixtures;
use Greendot\EshopBundle\Entity\Project\InformationBlock;
use Greendot\EshopBundle\Repository\Project\InformationBlockRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<InformationBlock>
 *
 * @method        InformationBlock|Proxy create(array|callable $attributes = [])
 * @method static InformationBlock|Proxy createOne(array $attributes = [])
 * @method static InformationBlock|Proxy find(object|array|mixed $criteria)
 * @method static InformationBlock|Proxy findOrCreate(array $attributes)
 * @method static InformationBlock|Proxy first(string $sortedField = 'id')
 * @method static InformationBlock|Proxy last(string $sortedField = 'id')
 * @method static InformationBlock|Proxy random(array $attributes = [])
 * @method static InformationBlock|Proxy randomOrCreate(array $attributes = [])
 * @method static InformationBlockRepository|RepositoryProxy repository()
 * @method static InformationBlock[]|Proxy[] all()
 * @method static InformationBlock[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static InformationBlock[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static InformationBlock[]|Proxy[] findBy(array $attributes)
 * @method static InformationBlock[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static InformationBlock[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<InformationBlock> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<InformationBlock> createOne(array $attributes = [])
 * @phpstan-method static Proxy<InformationBlock> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<InformationBlock> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<InformationBlock> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<InformationBlock> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<InformationBlock> random(array $attributes = [])
 * @phpstan-method static Proxy<InformationBlock> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<InformationBlock> repository()
 * @phpstan-method static list<Proxy<InformationBlock>> all()
// * // * @phpstan-method static list<Proxy<InformationBlock>> createMany(int $number, array|callable $attributes = [])
// * // * @phpstan-method static list<Proxy<InformationBlock>> createSequence(iterable|callable $sequence)
// * // * @phpstan-method static list<Proxy<InformationBlock>> findBy(array $attributes)
// * // * @phpstan-method static list<Proxy<InformationBlock>> randomRange(int $min, int $max, array $attributes = [])
// * // * @phpstan-method static list<Proxy<InformationBlock>> randomSet(int $number, array $attributes = [])
 */
final class InformationBlockFactory extends ModelFactory
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
        $name = ucwords(self::faker()->words(3, true));
        return [
            'name' => $name,
            'title' => ucwords(self::faker()->word()),
            'isActive' => 1, //self::faker()->boolean(),
            'isReusable' => self::faker()->boolean(),
            'text' => "<div>$name</div>",

            'information_block_type' => InformationBlockTypeFactory::random(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this// ->afterInstantiate(function(InformationBlock $informationBlock): void {})
            ;
    }

    protected static function getClass(): string
    {
        return InformationBlock::class;
    }
}
