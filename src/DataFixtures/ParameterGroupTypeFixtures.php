<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\ParameterGroupType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ParameterGroupTypeFixtures extends Fixture implements FixtureGroupInterface
{

    public function load(ObjectManager $manager): void
    {
        $dataValues = [
            ['id' => 1, 'name' => 'Produktové parametry'],
            ['id' => 2, 'name' => 'Blog'],
        ];

        foreach ($dataValues as $dataValue) {
            $object = new ParameterGroupType();
            $object->setName($dataValue['name']);
            $object->setSequence($dataValue['id']);
            $manager->persist($object);
        }

        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}