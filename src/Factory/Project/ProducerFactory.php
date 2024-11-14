<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Repository\Project\ProducerRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Producer>
 *
 * @method        Producer|Proxy create(array|callable $attributes = [])
 * @method static Producer|Proxy createOne(array $attributes = [])
 * @method static Producer|Proxy find(object|array|mixed $criteria)
 * @method static Producer|Proxy findOrCreate(array $attributes)
 * @method static Producer|Proxy first(string $sortedField = 'id')
 * @method static Producer|Proxy last(string $sortedField = 'id')
 * @method static Producer|Proxy random(array $attributes = [])
 * @method static Producer|Proxy randomOrCreate(array $attributes = [])
 * @method static ProducerRepository|RepositoryProxy repository()
 * @method static Producer[]|Proxy[] all()
 * @method static Producer[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Producer[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Producer[]|Proxy[] findBy(array $attributes)
 * @method static Producer[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Producer[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Producer> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Producer> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Producer> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Producer> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Producer> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Producer> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Producer> random(array $attributes = [])
 * @phpstan-method static Proxy<Producer> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Producer> repository()
 * @phpstan-method static list<Proxy<Producer>> all()
// * @phpstan-method static list<Proxy<Producer>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<Producer>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<Producer>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<Producer>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<Producer>> randomSet(int $number, array $attributes = [])
 */
final class ProducerFactory extends ModelFactory
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
        $companyName = ucwords(self::faker()->company);
        $html = "<div>$companyName</div>";
        return [
            'name' => $companyName,
            'menu_name' => $companyName,
            'title' => $companyName,
            'is_menu' => 0,
            'slug' => str_replace(' ', '-', strtolower($companyName)),
            'html' => $html,
            'description' => $html,
            'upload' => UploadFactory::random(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Producer $producer): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Producer::class;
    }
}
