<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Parameter>
 *
 * @method        Parameter|Proxy create(array|callable $attributes = [])
 * @method static Parameter|Proxy createOne(array $attributes = [])
 * @method static Parameter|Proxy find(object|array|mixed $criteria)
 * @method static Parameter|Proxy findOrCreate(array $attributes)
 * @method static Parameter|Proxy first(string $sortedField = 'id')
 * @method static Parameter|Proxy last(string $sortedField = 'id')
 * @method static Parameter|Proxy random(array $attributes = [])
 * @method static Parameter|Proxy randomOrCreate(array $attributes = [])
 * @method static ParameterRepository|RepositoryProxy repository()
 * @method static Parameter[]|Proxy[] all()
 * @method static Parameter[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Parameter[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Parameter[]|Proxy[] findBy(array $attributes)
 * @method static Parameter[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Parameter[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Parameter> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Parameter> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Parameter> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Parameter> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Parameter> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Parameter> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Parameter> random(array $attributes = [])
 * @phpstan-method static Proxy<Parameter> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Parameter> repository()
 * @phpstan-method static list<Proxy<Parameter>> all()
// * @phpstan-method static list<Proxy<Parameter>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<Parameter>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<Parameter>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<Parameter>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<Parameter>> randomSet(int $number, array $attributes = [])
 */
final class ParameterFactory extends ModelFactory
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
            'data' => rand(1, 25) * 10,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Parameter $parameter): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Parameter::class;
    }
}
