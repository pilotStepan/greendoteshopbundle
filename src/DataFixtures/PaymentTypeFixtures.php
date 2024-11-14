<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\BranchType;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Greendot\EshopBundle\Entity\Project\PaymentType;
use Greendot\EshopBundle\Factory\Project\HandlingPriceFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class PaymentTypeFixtures extends Fixture
{
    private array $dataArray = [
        1 => [
            'name' => 'Bankovním převodem',
            'icon' => 'svg-bank',
            'duration' => '15',
        ],
        2 => [
            'name' => 'Kartou online',
            'icon' => 'svg-creditcard',
            'duration' => '15',
        ],
        3 => [
            'name' => 'Dobírka',
            'icon' => 'svg-home',
            'duration' => '1',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new PaymentType();
            $object->setName($data['name']);
            $object->setDescription($data['name']);
            $object->setDescritionMail($data['name']);
            $object->setDescriptionDuration($data['name']);
            $object->setHtml('');
            $object->setIcon($data['icon']);
            $object->setDuration($data['duration']);
            $object->setSequence(1);
            $object->setCountry('CZ');
            $object->setIsEnabled(1);
            $object->setActionGroup(1);
            $manager->persist($object);

//            FIXME: doesnt work
//            HandlingPriceFactory::createOne([
//                'price' => rand(0,4) * 25,
//                'free_from_price' => rand(0,5) * 20,
//                'paymentType' => $object,
//            ]);
        }
        $manager->flush();
    }
}