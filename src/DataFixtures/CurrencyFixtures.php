<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\Currency;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CurrencyFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Česká koruna',
            'symbol' => 'Kč',
            'conversionRate' => 1,
            'rounding' => 0,
            'isDefault' => 1,
            'is_symbol_left' => 0,
        ],
        2 => [
            'name' => 'Euro',
            'symbol' => '€',
            'conversionRate' => 0.04,
            'rounding' => 1,
            'isDefault' => 0,
            'is_symbol_left' => 0,
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new Currency();
            $object->setName($data['name']);
            $object->setSymbol($data['symbol']);
            $object->setConversionRate($data['conversionRate']);
            $object->setRounding($data['rounding']);
            $object->setIsDefault($data['isDefault']);
            $object->setSymbolLeft($data['is_symbol_left']);
            $manager->persist($object);
        }

        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}
