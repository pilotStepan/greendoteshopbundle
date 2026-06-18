<?php

namespace Greendot\EshopBundle\Tests\App\Factory;

use Greendot\EshopBundle\Entity\Project\Review;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Review>
 */
final class ReviewFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Review::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'contents' => self::faker()->paragraph(),
            'stars' => self::faker()->numberBetween(1, 5),
            'positive' => true,
            // ReviewIsApprovedExtension restricts every Review GET/PATCH/PUT/DELETE query to
            // is_approved=true, with no admin bypass; unapproved reviews are invisible to all.
            'isApproved' => true,
            'product' => ProductFactory::new(),
        ];
    }
}
