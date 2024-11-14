<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ProducerFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProducerFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        ProducerFactory::createMany(15);
    }


    public function getDependencies(): array
    {
        return array(
            UploadFixtures::class,
        );
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
