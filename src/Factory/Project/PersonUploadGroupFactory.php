<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\PersonUploadGroup;
use Greendot\EshopBundle\Repository\Project\PersonUploadGroupRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<PersonUploadGroup>
 *
 * @method        PersonUploadGroup|Proxy create(array|callable $attributes = [])
 * @method static PersonUploadGroup|Proxy createOne(array $attributes = [])
 * @method static PersonUploadGroup|Proxy find(object|array|mixed $criteria)
 * @method static PersonUploadGroup|Proxy findOrCreate(array $attributes)
 * @method static PersonUploadGroup|Proxy first(string $sortedField = 'id')
 * @method static PersonUploadGroup|Proxy last(string $sortedField = 'id')
 * @method static PersonUploadGroup|Proxy random(array $attributes = [])
 * @method static PersonUploadGroup|Proxy randomOrCreate(array $attributes = [])
 * @method static PersonUploadGroupRepository|RepositoryProxy repository()
 * @method static PersonUploadGroup[]|Proxy[] all()
 * @method static PersonUploadGroup[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static PersonUploadGroup[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static PersonUploadGroup[]|Proxy[] findBy(array $attributes)
 * @method static PersonUploadGroup[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static PersonUploadGroup[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<PersonUploadGroup> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<PersonUploadGroup> createOne(array $attributes = [])
 * @phpstan-method static Proxy<PersonUploadGroup> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<PersonUploadGroup> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<PersonUploadGroup> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<PersonUploadGroup> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<PersonUploadGroup> random(array $attributes = [])
 * @phpstan-method static Proxy<PersonUploadGroup> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<PersonUploadGroup> repository()
 * @phpstan-method static list<Proxy<PersonUploadGroup>> all()
// * @phpstan-method static list<Proxy<PersonUploadGroup>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<PersonUploadGroup>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<PersonUploadGroup>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<PersonUploadGroup>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<PersonUploadGroup>> randomSet(int $number, array $attributes = [])
 */
final class PersonUploadGroupFactory extends ModelFactory
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
            // ->afterInstantiate(function(PersonUploadGroup $personUploadGroup): void {})
        ;
    }

    protected static function getClass(): string
    {
        return PersonUploadGroup::class;
    }
}
