<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/* @deprecated
 * PaymentAction was reworked.
 */
class PaymentActionFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Action test',
            'icon' => 'test',
        ],
        2 => [
            'name' => 'platba převodem',
            'icon' => '',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new PaymentAction();
            $object->setName($data['name']);
//            $object->setIcon($data['icon']); icon deleted
            $manager->persist($object);
        }
        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}