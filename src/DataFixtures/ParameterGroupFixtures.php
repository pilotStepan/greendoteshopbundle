<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ParameterGroupFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ParameterGroupFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{

    public function load(ObjectManager $manager): void
    {
        ParameterGroupFactory::createMany(10);
    }

    public function getDependencies(): array
    {
        return array(
            ParameterGroupTypeFixtures::class,
            ParameterGroupFilterTypeFixtures::class,
        );
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}