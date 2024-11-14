<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Transportation>
 *
 * @method        Transportation|Proxy create(array|callable $attributes = [])
 * @method static Transportation|Proxy createOne(array $attributes = [])
 * @method static Transportation|Proxy find(object|array|mixed $criteria)
 * @method static Transportation|Proxy findOrCreate(array $attributes)
 * @method static Transportation|Proxy first(string $sortedField = 'id')
 * @method static Transportation|Proxy last(string $sortedField = 'id')
 * @method static Transportation|Proxy random(array $attributes = [])
 * @method static Transportation|Proxy randomOrCreate(array $attributes = [])
 * @method static TransportationRepository|RepositoryProxy repository()
 * @method static Transportation[]|Proxy[] all()
 * @method static Transportation[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Transportation[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Transportation[]|Proxy[] findBy(array $attributes)
 * @method static Transportation[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Transportation[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Transportation> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Transportation> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Transportation> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Transportation> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Transportation> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Transportation> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Transportation> random(array $attributes = [])
 * @phpstan-method static Proxy<Transportation> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Transportation> repository()
 * @phpstan-method static list<Proxy<Transportation>> all()
// * // * @phpstan-method static list<Proxy<Transportation>> createMany(int $number, array|callable $attributes = [])
// * // * @phpstan-method static list<Proxy<Transportation>> createSequence(iterable|callable $sequence)
// * // * @phpstan-method static list<Proxy<Transportation>> findBy(array $attributes)
// * // * @phpstan-method static list<Proxy<Transportation>> randomRange(int $min, int $max, array $attributes = [])
// * // * @phpstan-method static list<Proxy<Transportation>> randomSet(int $number, array $attributes = [])
 */
final class TransportationFactory extends ModelFactory
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
        $name = ucwords(self::faker()->words(2, True));
        $duration = rand(1, 5);
        return [
            'Name' => $name,
            'country' => 'CZ',
            'description' => $name,
            'description_duration' => $duration,
            'description_mail' => $name,
            'duration' => $duration,
            'price' => rand(5, 20) * 10 - 1,
            'free_from_price' => rand(1,5) * 500,
            'vat' => 21,
            'html' => "<div>$name</div>",
            'icon' => 'placeholder.svg',
            'section' => 0,
            'squence' => self::faker()->randomNumber(),
            'is_enabled' => true,
            'state_url' => $name,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this// ->afterInstantiate(function(Transportation $transportation): void {})
            ;
    }

    protected static function getClass(): string
    {
        return Transportation::class;
    }
}
