<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\PriceFactory;
use Greendot\EshopBundle\Factory\Project\ProductFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PriceFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $products = ProductFactory::all();

        foreach ($products as $product) {
            // Create price for each variant
            $productVariants = $product->getProductVariants();
            $price = rand(10, 500) * 10 - 1;
            $minPrice = $price * 0.666;
            foreach ($productVariants as $productVariant) {
                // regular price
                PriceFactory::createOne([
                    'productVariant' => $productVariant,
                    'price' => $price,
                    'minPrice' => $minPrice,
                ]);
                // discount price
                PriceFactory::createOne([
                    'productVariant' => $productVariant,
                    'price' => $price,
                    'minPrice' => $minPrice,
                    'discount' => $minPrice,
                ]);
            }
        }
    }

    public function getDependencies(): array
    {
        return [
            ProductVariantFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
