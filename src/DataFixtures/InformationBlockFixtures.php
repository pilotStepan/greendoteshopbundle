<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\CategoryFactory;
use Greendot\EshopBundle\Factory\Project\CategoryInformationBlockFactory;
use Greendot\EshopBundle\Factory\Project\InformationBlockFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InformationBlockFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        InformationBlockFactory::createMany(15);

        $categories = CategoryFactory::all();
        foreach ($categories as $category) {
            CategoryInformationBlockFactory::createOne([
                'category' => $category,
                'informationBlock' => InformationBlockFactory::random(),
            ]);
        }
    }

    public function getDependencies(): array
    {
        return array(
            InformationBlockTypeFixtures::class,
        );
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
