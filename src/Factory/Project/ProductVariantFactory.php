<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Availability;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\AvailabilityRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ProductVariant>
 *
 * @method        ProductVariant|Proxy create(array|callable $attributes = [])
 * @method static ProductVariant|Proxy createOne(array $attributes = [])
 * @method static ProductVariant|Proxy find(object|array|mixed $criteria)
 * @method static ProductVariant|Proxy findOrCreate(array $attributes)
 * @method static ProductVariant|Proxy first(string $sortedField = 'id')
 * @method static ProductVariant|Proxy last(string $sortedField = 'id')
 * @method static ProductVariant|Proxy random(array $attributes = [])
 * @method static ProductVariant|Proxy randomOrCreate(array $attributes = [])
 * @method static ProductVariantRepository|RepositoryProxy repository()
 * @method static ProductVariant[]|Proxy[] all()
 * @method static ProductVariant[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ProductVariant[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ProductVariant[]|Proxy[] findBy(array $attributes)
 * @method static ProductVariant[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ProductVariant[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ProductVariant> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ProductVariant> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ProductVariant> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ProductVariant> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ProductVariant> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductVariant> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ProductVariant> random(array $attributes = [])
 * @phpstan-method static Proxy<ProductVariant> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ProductVariant> repository()
 * @phpstan-method static list<Proxy<ProductVariantFixtures>> all()
// * @phpstan-method static list<Proxy<ProductVariantFixtures>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<ProductVariantFixtures>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<ProductVariantFixtures>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<ProductVariantFixtures>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<ProductVariantFixtures>> randomSet(int $number, array $attributes = [])
// */
final class ProductVariantFactory extends ModelFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
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
        $availabilityRepository = $this->getAvailabilityRepository();
        $availableItems = $availabilityRepository->findAll();

        if (empty($availableItems)) {
            throw new \RuntimeException('No availability items found.');
        }

        $randomAvailability = $availableItems[array_rand($availableItems)];

        return [
            'availability' => $randomAvailability,
            'stock' => rand(1, 100),
            'avgRestockDays' => rand(1, 4) * 7,
            'isActive' => 1,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ProductVariantFixtures $productVariant): void {})
            ;
    }

    protected static function getClass(): string
    {
        return ProductVariant::class;
    }

    private function getAvailabilityRepository(): AvailabilityRepository
    {
        return $this->entityManager->getRepository(Availability::class);
    }
}
