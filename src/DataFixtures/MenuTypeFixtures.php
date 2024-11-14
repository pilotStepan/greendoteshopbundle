<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Factory\Project\CategoryFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MenuTypeFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        1 => [
            'name' => 'Hlavní menu',
            'template' => '/web/menu/main.html.twig',
        ],
        2 => [
            'name' => 'Sekundární menu',
            'template' => '/web/menu/secondary.html.twig',
        ],
        3 => [
            'name' => 'Horní menu',
            'template' => '/web/menu/top.html.twig',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new MenuType();
            $object->setName($data['name']);
            $object->setTemplate($data['template']);
            $object->setControllerName('web_menu');
            $manager->persist($object);
        }
        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}
