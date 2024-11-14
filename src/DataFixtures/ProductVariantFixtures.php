<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ProductFactory;
use Greendot\EshopBundle\Factory\Project\ProductVariantFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory as FakerFactory;

class ProductVariantFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = FakerFactory::create();
        $products = ProductFactory::all();

        foreach ($products as $product) {
            for ($i = 0; $i < rand(1, 3); $i++) {
                ProductVariantFactory::createOne([
                    'name' => $product->getName() . '- ' . $faker->word(),
                    'product' => $product,
                    'upload' => $product->getUpload(),
                ]);
            }
        }
    }

    public function getDependencies(): array
    {
        return [
            ProductFixtures::class,
            UploadFixtures::class,
            ParameterGroupFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
