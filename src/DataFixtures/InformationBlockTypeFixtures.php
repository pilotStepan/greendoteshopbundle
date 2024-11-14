<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\InformationBlockTypeFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InformationBlockTypeFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        InformationBlockTypeFactory::createMany(5);

        InformationBlockTypeFactory::createOne([
            'name' => 'Defaultní blok',
        ]);
    }

    public function getDependencies(): array
    {
        return array(
            CategoryFixtures::class,
        );
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
