<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\ProductType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ProductTypeFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $productTypes = [
            ['id' => 1, 'name' => 'Standard'],
            ['id' => 2, 'name' => 'Dárkový certifikát'],
            ['id' => 3, 'name' => 'Software'],
        ];

        foreach ($productTypes as $typeData) {
            $productType = new ProductType();
            $productType->setName($typeData['name']);
            $manager->persist($productType);
        }

        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}