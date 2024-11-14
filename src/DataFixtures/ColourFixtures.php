<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\Colour;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ColourFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // COLOUR
        $colourBlack = new Colour();
        $colourBlack->setName('Černá');
        $colourBlack->setHex('#000000');
        $colourBlack->setSequence(1);
        $manager->persist($colourBlack);

        $colourWhite = new Colour();
        $colourWhite->setName('Bílá');
        $colourWhite->setHex('#FFFFFF');
        $colourWhite->setSequence(2);
        $manager->persist($colourWhite);

        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}
