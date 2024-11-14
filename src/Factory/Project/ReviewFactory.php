<?php

namespace Greendot\EshopBundle\Factory\Project;

use Greendot\EshopBundle\Entity\Project\Review;
use Greendot\EshopBundle\Repository\Project\ReviewRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Review>
 *
 * @method        Review|Proxy                              create(array|callable $attributes = [])
 * @method static Review|Proxy                              createOne(array $attributes = [])
 * @method static Review|Proxy                              find(object|array|mixed $criteria)
 * @method static Review|Proxy                              findOrCreate(array $attributes)
 * @method static Review|Proxy                              first(string $sortedField = 'id')
 * @method static Review|Proxy                              last(string $sortedField = 'id')
 * @method static Review|Proxy                              random(array $attributes = [])
 * @method static Review|Proxy                              randomOrCreate(array $attributes = [])
 * @method static ReviewRepository|ProxyRepositoryDecorator repository()
 * @method static Review[]|Proxy[]                          all()
 * @method static Review[]|Proxy[]                          createMany(int $number, array|callable $attributes = [])
 * @method static Review[]|Proxy[]                          createSequence(iterable|callable $sequence)
 * @method static Review[]|Proxy[]                          findBy(array $attributes)
 * @method static Review[]|Proxy[]                          randomRange(int $min, int $max, array $attributes = [])
 * @method static Review[]|Proxy[]                          randomSet(int $number, array $attributes = [])
 */
final class ReviewFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return Review::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable
    {
        $level = ['beginner', 'intermediate', 'advanced'];
        $type = ['general', 'specific'];
        $randomLevel = $level[array_rand($level)];
        $randomType = $type[array_rand($type)];
        return [
            'reviewerName' => self::faker()->name(),
            'reviewerEmail' => self::faker()->email(),
            'contents' => self::faker()->text(),
            'date' => self::faker()->dateTime(),
            'is_approved' => 1, // self::faker()->boolean(),
            'positive' => 1, // self::faker()->boolean(),
            'product' => ProductFactory::random(),
            'stars' => rand(1, 5),
            'reviewParameters' => json_encode([
                'level' => [$randomLevel],
                'type' => [$randomType]
            ]),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Review $review): void {})
        ;
    }
}
