<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\ReviewPoints;
use Greendot\EshopBundle\Repository\Project\ReviewPointsRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<ReviewPoints>
 *
 * @method        ReviewPoints|Proxy                              create(array|callable $attributes = [])
 * @method static ReviewPoints|Proxy                              createOne(array $attributes = [])
 * @method static ReviewPoints|Proxy                              find(object|array|mixed $criteria)
 * @method static ReviewPoints|Proxy                              findOrCreate(array $attributes)
 * @method static ReviewPoints|Proxy                              first(string $sortedField = 'id')
 * @method static ReviewPoints|Proxy                              last(string $sortedField = 'id')
 * @method static ReviewPoints|Proxy                              random(array $attributes = [])
 * @method static ReviewPoints|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ReviewPointsRepository|ProxyRepositoryDecorator repository()
 * @method static ReviewPoints[]|Proxy[]                          all()
 * @method static ReviewPoints[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static ReviewPoints[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static ReviewPoints[]|Proxy[]                          findBy(array $attributes)
 * @method static ReviewPoints[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static ReviewPoints[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ReviewPointsFactory extends PersistentProxyObjectFactory
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

    public static function class(): string
    {
        return ReviewPoints::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        return [
            'text' => self::faker()->text(255),
            'type' => self::faker()->boolean(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(ReviewPoints $reviewPoints): void {})
        ;
    }
}
