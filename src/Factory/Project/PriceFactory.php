<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Price>
 *
 * @method        Price|Proxy create(array|callable $attributes = [])
 * @method static Price|Proxy createOne(array $attributes = [])
 * @method static Price|Proxy find(object|array|mixed $criteria)
 * @method static Price|Proxy findOrCreate(array $attributes)
 * @method static Price|Proxy first(string $sortedField = 'id')
 * @method static Price|Proxy last(string $sortedField = 'id')
 * @method static Price|Proxy random(array $attributes = [])
 * @method static Price|Proxy randomOrCreate(array $attributes = [])
 * @method static PriceRepository|RepositoryProxy repository()
 * @method static Price[]|Proxy[] all()
 * @method static Price[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Price[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Price[]|Proxy[] findBy(array $attributes)
 * @method static Price[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Price[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Price> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Price> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Price> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Price> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Price> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Price> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Price> random(array $attributes = [])
 * @phpstan-method static Proxy<Price> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Price> repository()
 * @phpstan-method static list<Proxy<Price>> all()
// * @phpstan-method static list<Proxy<Price>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<Price>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<Price>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<Price>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<Price>> randomSet(int $number, array $attributes = [])
 */
final class PriceFactory extends ModelFactory
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
        $currentDate = new \DateTime();
        return [
            'minimalAmount' => 1,
            'created' => $currentDate,
            'validFrom' => $currentDate,
            'vat' => 21,
            'isPackage' => 0,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Price $price): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Price::class;
    }
}
