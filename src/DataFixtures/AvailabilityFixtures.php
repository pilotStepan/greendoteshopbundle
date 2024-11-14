<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\Availability;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class AvailabilityFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Skladem',
        ],
        2 => [
            'name' => 'Vyprodáno',
        ],
        3 => [
            'name' => 'Na cestě',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new Availability();
            $object->setName($data['name']);
            $object->setDescription($data['name']);
            $manager->persist($object);
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['static'];
    }
}
