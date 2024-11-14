<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\UploadGroupFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class UploadGroupFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager): void
    {
        UploadGroupFactory::createMany(10);
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}