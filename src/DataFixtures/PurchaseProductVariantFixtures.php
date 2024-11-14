<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ProductVariantFactory;
use Greendot\EshopBundle\Factory\Project\PurchaseFactory;
use Greendot\EshopBundle\Factory\Project\PurchaseProductVariantFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PurchaseProductVariantFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $purchases = PurchaseFactory::all();

        foreach ($purchases as $purchase) {
            $productVariants = ProductVariantFactory::randomRange(1, 5);
            foreach ($productVariants as $productVariant) {
                $purchaseVariant = PurchaseProductVariantFactory::createOne([
                    'purchase' => $purchase,
                    'productVariant' => $productVariant,
                ]);
            }
        }
    }

    public function getDependencies(): array
    {
        return [
            ProductVariantFixtures::class,
            PurchaseFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
