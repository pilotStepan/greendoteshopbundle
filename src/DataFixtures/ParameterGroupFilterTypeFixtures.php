<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\ParameterGroupFilterType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ParameterGroupFilterTypeFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager): void
    {
        $dataValues = [
            ['id' => 1, 'name' => 'range'],
            ['id' => 2, 'name' => 'color'],
            ['id' => 3, 'name' => 'single_select'],
            ['id' => 4, 'name' => 'multiple_select'],
        ];

        foreach ($dataValues as $dataValue) {
            $object = new ParameterGroupFilterType();
            $object->setName($dataValue['name']);
            $manager->persist($object);
        }

        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}