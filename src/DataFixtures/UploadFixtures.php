<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\UploadFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UploadFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager): void
    {
        UploadFactory::createMany(50);
    }

    public function getDependencies(): array
    {
        return [
            UploadGroupFixtures::class,
        ];
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}