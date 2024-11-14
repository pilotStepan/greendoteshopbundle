<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SubMenuTypeFixtures extends Fixture implements FixtureGroupInterface
{
    private array $subMenuTypes = [
        1 => [
            'name' => 'E-shop',
        ],
        2 => [
            'name' => 'Katalog',
        ],
        3 => [
            'name' => 'Klasické',
        ],
        4 => [
            'name' => 'Odvětví',
        ],
        5 => [
            'name' => 'Služby',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach ($this->subMenuTypes as $id => $data) {
            $subMenuType = new SubMenuType();
            $subMenuType->setName($data['name']);
            $subMenuType->setTemplate('/web/submenu/submenu.html.twig');
            $subMenuType->setControllerName('Greendot\EshopBundle\Controller\Web\MenuController::generateSubMenu');
            $manager->persist($subMenuType);
        }
        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}
