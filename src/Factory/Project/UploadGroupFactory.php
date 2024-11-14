<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\UploadGroup;
use Greendot\EshopBundle\Repository\Project\UploadGroupRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<UploadGroup>
 *
 * @method        UploadGroup|Proxy create(array|callable $attributes = [])
 * @method static UploadGroup|Proxy createOne(array $attributes = [])
 * @method static UploadGroup|Proxy find(object|array|mixed $criteria)
 * @method static UploadGroup|Proxy findOrCreate(array $attributes)
 * @method static UploadGroup|Proxy first(string $sortedField = 'id')
 * @method static UploadGroup|Proxy last(string $sortedField = 'id')
 * @method static UploadGroup|Proxy random(array $attributes = [])
 * @method static UploadGroup|Proxy randomOrCreate(array $attributes = [])
 * @method static UploadGroupRepository|RepositoryProxy repository()
 * @method static UploadGroup[]|Proxy[] all()
 * @method static UploadGroup[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static UploadGroup[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static UploadGroup[]|Proxy[] findBy(array $attributes)
 * @method static UploadGroup[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UploadGroup[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<UploadGroup> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<UploadGroup> createOne(array $attributes = [])
 * @phpstan-method static Proxy<UploadGroup> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<UploadGroup> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<UploadGroup> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<UploadGroup> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<UploadGroup> random(array $attributes = [])
 * @phpstan-method static Proxy<UploadGroup> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<UploadGroup> repository()
 * @phpstan-method static list<Proxy<UploadGroup>> all()
// * @phpstan-method static list<Proxy<UploadGroup>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<UploadGroup>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<UploadGroup>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<UploadGroup>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<UploadGroup>> randomSet(int $number, array $attributes = [])
 */
final class UploadGroupFactory extends ModelFactory
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
        static $type = 0;
        $type++;
        return [
            'type' => $type,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(UploadGroup $uploadGroup): void {})
        ;
    }

    protected static function getClass(): string
    {
        return UploadGroup::class;
    }
}
