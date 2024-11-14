<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\BranchType;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

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