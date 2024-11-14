<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Upload;
use Greendot\EshopBundle\Repository\Project\UploadRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<Upload>
 *
 * @method        Upload|Proxy create(array|callable $attributes = [])
 * @method static Upload|Proxy createOne(array $attributes = [])
 * @method static Upload|Proxy find(object|array|mixed $criteria)
 * @method static Upload|Proxy findOrCreate(array $attributes)
 * @method static Upload|Proxy first(string $sortedField = 'id')
 * @method static Upload|Proxy last(string $sortedField = 'id')
 * @method static Upload|Proxy random(array $attributes = [])
 * @method static Upload|Proxy randomOrCreate(array $attributes = [])
 * @method static UploadRepository|RepositoryProxy repository()
 * @method static Upload[]|Proxy[] all()
 * @method static Upload[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Upload[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static Upload[]|Proxy[] findBy(array $attributes)
 * @method static Upload[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static Upload[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<Upload> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<Upload> createOne(array $attributes = [])
 * @phpstan-method static Proxy<Upload> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<Upload> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<Upload> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<Upload> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<Upload> random(array $attributes = [])
 * @phpstan-method static Proxy<Upload> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<Upload> repository()
 * @phpstan-method static list<Proxy<Upload>> all()
// * @phpstan-method static list<Proxy<Upload>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<Upload>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<Upload>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<Upload>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<Upload>> randomSet(int $number, array $attributes = [])
 */
final class UploadFactory extends ModelFactory
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
//        $name = rand(1000000, 9999999) . "_" . rand(1,9);
        $name = 'placeholder';
        $extension = 'jpg';
        return [
            'name' => $name,
            'extension' => $extension,
            'mime' => 'image/jpeg',
            'path' => '/uploads/images/' . $name . '.' . $extension,
            'originalName' => $name . '.' . $extension,
            'width' => 1000,
            'height' => 1000,
            'sequence' => 0,
            'created' => new \DateTime(),
            'uploadGroup' => UploadGroupFactory::random(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(Upload $upload): void {})
        ;
    }

    protected static function getClass(): string
    {
        return Upload::class;
    }
}
