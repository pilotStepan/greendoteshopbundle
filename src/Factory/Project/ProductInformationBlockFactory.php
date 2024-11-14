<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ProductInformationBlock;
use Greendot\EshopBundle\Repository\Project\ProductInformationBlockRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ProductInformationBlock>
 *
 * @method        ProductInformationBlock|Proxy create(array|callable $attributes = [])
 * @method static ProductInformationBlock|Proxy createOne(array $attributes = [])
 * @method static ProductInformationBlock|Proxy find(object|array|mixed $criteria)
 * @method static ProductInformationBlock|Proxy findOrCreate(array $attributes)
 * @method static ProductInformationBlock|Proxy first(string $sortedField = 'id')
 * @method static ProductInformationBlock|Proxy last(string $sortedField = 'id')
 * @method static ProductInformationBlock|Proxy random(array $attributes = [])
 * @method static ProductInformationBlock|Proxy randomOrCreate(array $attributes = [])
 * @method static ProductInformationBlockRepository|RepositoryProxy repository()
 * @method static ProductInformationBlock[]|Proxy[] all()
 * @method static ProductInformationBlock[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProductInformationBlock[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ProductInformationBlock[]|Proxy[] findBy(array $attributes)
 * @method static ProductInformationBlock[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ProductInformationBlock[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ProductInformationBlock> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ProductInformationBlock> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ProductInformationBlock> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ProductInformationBlock> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ProductInformationBlock> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductInformationBlock> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductInformationBlock> random(array $attributes = [])
 * @phpstan-method static Proxy<ProductInformationBlock> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ProductInformationBlock> repository()
 * @phpstan-method static list<Proxy<ProductInformationBlock>> all()
// * @phpstan-method static list<Proxy<ProductInformationBlock>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<ProductInformationBlock>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<ProductInformationBlock>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<ProductInformationBlock>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<ProductInformationBlock>> randomSet(int $number, array $attributes = [])
 */
final class ProductInformationBlockFactory extends ModelFactory
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
            'product' => ProductFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ProductInformationBlock $productInformationBlock): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ProductInformationBlock::class;
    }
}
