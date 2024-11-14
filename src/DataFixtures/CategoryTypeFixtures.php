<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\CategoryType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CategoryTypeFixtures extends Fixture implements FixtureGroupInterface
{
    private array $dataArray = [
        [
            'name' => 'Stránka',
            'template' => 'web/pages/empty_page.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage',
        ],
        [
            'name' => 'Category',
            'template' => 'shop/category/index.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Shop\CategoryController::index',
        ],
        [
            'name' => 'SubCategory',
            'template' => 'shop/category/detail.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Shop\CategoryController::index',
        ],
        [
            'name' => 'Secondary page',
            'template' => 'web/pages/empty_page_fluid.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage',
        ],
        [
            'name' => 'Subcategorie',
            'template' => 'web/pages/empty_page.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage',
        ],
        [
            'name' => 'Článkek',
            'template' => 'web/pages/empty_page.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage',
        ],
        [
            'name' => 'Webinář',
            'template' => 'web/pages/empty_page.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\GeneralPageController::getPage',
        ],
        [
            'name' => 'Odvětví',
            'template' => 'web/industry_page/index.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\IndustryPageController::index',
        ],
        [
            'name' => 'Aplikace',
            'template' => 'web/application/index.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\ApplicationPageController::index',
        ],
        [
            'name' => 'Aplikační zpráva',
            'template' => 'web/application/message.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Web\ApplicationPageController::message',
        ],
        [
            'name' => 'Slevy',
            'template' => 'shop/category/discounts.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Shop\DiscountsController::index',
        ],
        [
            'name' => 'Znacky',
            'template' => 'shop/producer/list.html.twig',
            'controller' => 'Greendot\EshopBundle\Controller\Shop\ProducerController::index',
        ],
    ];
    public function load(ObjectManager $manager): void
    {
        foreach ($this->dataArray as $id => $data) {
            $object = new CategoryType();
            $object->setName($data['name']);
            $object->setTemplate($data['template']);
            $object->setControllerName($data['controller']);
            $manager->persist($object);
        }
        $manager->flush();
    }
    public static function getGroups(): array
    {
        return ['static'];
    }
}
