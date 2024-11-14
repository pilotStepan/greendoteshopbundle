<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ProductFactory;
use Greendot\EshopBundle\Factory\Project\ReviewFactory;
use Greendot\EshopBundle\Factory\Project\ReviewPointsFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReviewFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $reviews = [];
        $products = ProductFactory::all();

        foreach ($products as $product) {
            $reviews = array_merge(ReviewFactory::createMany(rand(1, 5), [
                'product' => $product,
            ]));
        }

        foreach ($reviews as $review) {
            ReviewPointsFactory::createMany(rand(1, 5), [
                'review' => $review,
            ]);
        }
    }

    public function getDependencies(): array
    {
        return [
            ProductFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
