<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\ParameterGroupType;
use Greendot\EshopBundle\Repository\Project\ParameterGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<ParameterGroup>
 *
 * @method        ParameterGroup|Proxy create(array|callable $attributes = [])
 * @method static ParameterGroup|Proxy createOne(array $attributes = [])
 * @method static ParameterGroup|Proxy find(object|array|mixed $criteria)
 * @method static ParameterGroup|Proxy findOrCreate(array $attributes)
 * @method static ParameterGroup|Proxy first(string $sortedField = 'id')
 * @method static ParameterGroup|Proxy last(string $sortedField = 'id')
 * @method static ParameterGroup|Proxy random(array $attributes = [])
 * @method static ParameterGroup|Proxy randomOrCreate(array $attributes = [])
 * @method static ParameterGroupRepository|RepositoryProxy repository()
 * @method static ParameterGroup[]|Proxy[] all()
 * @method static ParameterGroup[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static ParameterGroup[]|Proxy[] createSequence(iterable|callable $sequence)
 * @method static ParameterGroup[]|Proxy[] findBy(array $attributes)
 * @method static ParameterGroup[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ParameterGroup[]|Proxy[] randomSet(int $number, array $attributes = [])
 *
 * @phpstan-method        Proxy<ParameterGroup> create(array|callable $attributes = [])
 * @phpstan-method static Proxy<ParameterGroup> createOne(array $attributes = [])
 * @phpstan-method static Proxy<ParameterGroup> find(object|array|mixed $criteria)
 * @phpstan-method static Proxy<ParameterGroup> findOrCreate(array $attributes)
 * @phpstan-method static Proxy<ParameterGroup> first(string $sortedField = 'id')
 * @phpstan-method static Proxy<ParameterGroup> last(string $sortedField = 'id')
 * @phpstan-method static Proxy<ParameterGroup> random(array $attributes = [])
 * @phpstan-method static Proxy<ParameterGroup> randomOrCreate(array $attributes = [])
 * @phpstan-method static RepositoryProxy<ParameterGroup> repository()
 * @phpstan-method static list<Proxy<ParameterGroupFixtures>> all()
// * @phpstan-method static list<Proxy<ParameterGroupFixtures>> createMany(int $number, array|callable $attributes = [])
// * @phpstan-method static list<Proxy<ParameterGroupFixtures>> createSequence(iterable|callable $sequence)
// * @phpstan-method static list<Proxy<ParameterGroupFixtures>> findBy(array $attributes)
// * @phpstan-method static list<Proxy<ParameterGroupFixtures>> randomRange(int $min, int $max, array $attributes = [])
// * @phpstan-method static list<Proxy<ParameterGroupFixtures>> randomSet(int $number, array $attributes = [])
// */
final class ParameterGroupFactory extends ModelFactory
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
        $filterType = ParameterGroupFilterTypeFactory::random();
        $type = $this->entityManager->getRepository(ParameterGroupType::class)->findOneBy(['name' => 'Produktové parametry']);
        $units = ['ml', 'mm', 'g', 'kg', 'm', 'cm', 'l', 'm²', 'm³', 'm/s', 'm/s²'];
        return [
            'name' => self::faker()->word(),
            'unit' => self::faker()->randomElement($units),
            'isProductParameter' => true,
            'type' => $type,
            'parameterGroupFilterType' => $filterType,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(ParameterGroupFixtures $parameterGroup): void {})
        ;
    }

    protected static function getClass(): string
    {
        return ParameterGroup::class;
    }
}
