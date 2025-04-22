<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\TransportationAction;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/* @deprecated
 * This belongs to TransportationGroup from now on.
 * Fixtures should be updated.
 */
class TransportationActionFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Osobně',
            'icon' => 'svg-home',
            'country' => 'CZ',
        ],
        2 => [
            'name' => 'Výdejní místo',
            'icon' => 'svg-location',
            'country' => 'CZ',
        ],
        3 => [
            'name' => 'Doprava až k vám',
            'icon' => 'svg-transport',
            'country' => 'CZ',
            ],
        4 => [
            'name' => 'Packeta na adresu - Slovensko',
            'icon' => 'svg-transport',
            'country' => 'SK',
        ],
        5 => [
            'name' => 'Packeta na výdejní místo - Slovensko',
            'icon' => 'svg-home',
            'country' => 'SK',
        ],
        6 => [
            'name' => 'DPD - Slovensko',
            'icon' => 'svg-home',
            'country' => 'SK',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new TransportationAction();
            $object->setName($data['name']);
            $object->setIcon($data['icon']);
            $object->setCountry($data['country']);
            $manager->persist($object);
        }
        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}