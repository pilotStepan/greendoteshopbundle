<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\BranchType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class BranchTypeFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Balikovna',
            'icon' => 'https://www.yogashop.cz/images/doprava/marker-do-balikovny.png',
        ],
        2 => [
            'name' => 'Packeta',
            'icon' => 'https://www.yogashop.cz/images/doprava/marker-zasilkovna.png',

        ],
        3 => [
            'name' => 'Vlastni',
            'icon' => 'https://www.yogashop.cz/images/doprava/marker-ys.png',
        ],
        4 => [
            'name' => 'Pošta',
            'icon' => '',
        ],
        5 => [
            'name' => 'AlzaBox',
            'icon' => '',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new BranchType();
            $object->setName($data['name']);
            $object->setIcon($data['icon']);
            $manager->persist($object);
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['static'];
    }
}