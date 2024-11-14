<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\PersonInformationBlock;
use Greendot\EshopBundle\Repository\Project\PersonInformationBlockRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<PersonInformationBlock>
 *
 * @method        PersonInformationBlock|Proxy create(array|callable $attributes = [])
 * @method static PersonInformationBlock|Proxy createOne(array $attributes = [])
 * @method static PersonInformationBlock|Proxy find(object|array|mixed $criteria)
 * @method static PersonInformationBlock|Proxy findOrCreate(array $attributes)
 * @method static PersonInformationBlock|Proxy first(string $sortedField = 'id')
 * @method static PersonInformationBlock|Proxy last(string $sortedField = 'id')
 * @method static PersonInformationBlock|Proxy random(array $attributes = [])
 * @method static PersonInformationBlock|Proxy randomOrCreate(array $attributes = [])
 * @method static PersonInformationBlockRepository|RepositoryProxy repository()
 * @method static PersonInformationBlock[]|Proxy[] all()
 * @method static PersonInformationBlock[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PersonInformationBlock[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static PersonInformationBlock[]|Proxy[] findBy(array $attributes)
 * @method static PersonInformationBlock[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PersonInformationBlock[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PersonInformationBlock> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PersonInformationBlock> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PersonInformationBlock> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PersonInformationBlock> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PersonInformationBlock> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PersonInformationBlock> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PersonInformationBlock> random(array $attributes = [])
 * @phpstan-method static Proxy<PersonInformationBlock> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<PersonInformationBlock> repository()
 * @phpstan-method static list<Proxy<PersonInformationBlock>> all()
// * @phpstan-method static list<Proxy<PersonInformationBlock>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<PersonInformationBlock>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<PersonInformationBlock>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<PersonInformationBlock>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<PersonInformationBlock>> randomSet(int $number, array $attributes = [])
 */
final class PersonInformationBlockFactory extends ModelFactory
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
            'informationBlock' => InformationBlockFactory::new(),
            'person' => PersonFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(PersonInformationBlock $personInformationBlock): void {})
        ;
    }

    protected static function getClass(): string
    {
        return PersonInformationBlock::class;
    }
}
