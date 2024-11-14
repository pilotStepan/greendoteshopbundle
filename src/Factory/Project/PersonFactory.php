<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Person;
use Greendot\EshopBundle\Repository\Project\PersonRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Person>
 *
 * @method        Person|Proxy create(array|callable $attributes = [])
 * @method static Person|Proxy createOne(array $attributes = [])
 * @method static Person|Proxy find(object|array|mixed $criteria)
 * @method static Person|Proxy findOrCreate(array $attributes)
 * @method static Person|Proxy first(string $sortedField = 'id')
 * @method static Person|Proxy last(string $sortedField = 'id')
 * @method static Person|Proxy random(array $attributes = [])
 * @method static Person|Proxy randomOrCreate(array $attributes = [])
 * @method static PersonRepository|RepositoryProxy repository()
 * @method static Person[]|Proxy[] all()
 * @method static Person[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Person[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Person[]|Proxy[] findBy(array $attributes)
 * @method static Person[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Person[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Person> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Person> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Person> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Person> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Person> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Person> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Person> random(array $attributes = [])
 * @phpstan-method static Proxy<Person> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Person> repository()
 * @phpstan-method static list<Proxy<Person>> all()
// * @phpstan-method static list<Proxy<Person>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<Person>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<Person>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<Person>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<Person>> randomSet(int $number, array $attributes = [])
 */
final class PersonFactory extends ModelFactory
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
        $name = self::faker()->firstName();
        $surname = self::faker()->lastName();
        return [
            'name' => $name,
            'surname' => $surname,
            'department' => self::faker()->word(),
            'email' => sprintf('%s.%s@example.com', strtolower($name), strtolower($surname)), //johndoe@example.com
            'slug' => sprintf('%s.-%s@example.com', strtolower($name), strtolower($surname)), //john-doe
            'phone' => self::faker()->numberBetween(100000000, 999999999),
            'isActive' => self::faker()->boolean(),
            'titleBefore' => self::faker()->randomElement(['', 'Dr.', 'Prof.', 'Mgr.', 'Ing.']),
//            'userID' => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Person $person): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Person::class;
    }
}
