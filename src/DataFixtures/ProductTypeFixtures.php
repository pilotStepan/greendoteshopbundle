<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\ProductType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Greendot\EshopBundle\Enum\ProductTypeEnum;

class ProductTypeFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $productTypes = [
            ['id' => ProductTypeEnum::Standard->value, 'name' => 'Standard'],
            ['id' => ProductTypeEnum::Voucher->value, 'name' => 'Dárkový certifikát'],
            ['id' => ProductTypeEnum::Software->value, 'name' => 'Software'],
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