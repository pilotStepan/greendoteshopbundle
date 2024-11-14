<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Factory\Project\CategoryProductFactory;
use Greendot\EshopBundle\Factory\Project\ProductFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager){}

    public function load(ObjectManager $manager): void
    {
        $menuType = $this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Hlavní menu']);
        $eshopCategories = $this->entityManager->getRepository(Category::class)->findMenuCategories($menuType);

        foreach ($eshopCategories as $eshopCategory) {
            $products = ProductFactory::createMany(rand(1, 5));
            foreach ($products as $product) {
                CategoryProductFactory::createOne([
                    'category' => $eshopCategory,
                    'product' => $product,
                ]);
            }
            $eshopSubCategories = $this->entityManager->getRepository(Category::class)->findSubMenuCategories($eshopCategory, $menuType);
            foreach ($eshopSubCategories as $eshopSubCategory) {
                $products = ProductFactory::createMany(rand(1, 5));
                foreach ($products as $product) {
                    CategoryProductFactory::createOne([
                        'category' => $eshopSubCategory,
                        'product' => $product,
                    ]);
                }
            }
        }



//        $products = ProductFactory::all();

//        foreach ($products as $product) {

//            // Assign product to 1-25 people
//            $persons = PersonFactory::randomRange(1, 25);
//            foreach ($persons as $person) {
//                ProductPersonFactory::createOne([
//                    'product' => $product,
//                    'person' => $person,
//                    'sequence' => 1,
//                ]);
//            }

//            // Assign product to 1-5 information block
//            $informationBlocks = InformationBlockFactory::randomRange(1, 5);
//            foreach ($informationBlocks as $informationBlock) {
//                ProductInformationBlockFactory::createOne([
//                    'product' => $product,
//                    'informationBlock' => $informationBlock,
//                    'sequence' => 1,
//                ]);
//            }

//            // Assign product to 1-4 upload groups
//            $uploadGroups = UploadGroupFactory::randomRange(1, 4);
//            foreach ($uploadGroups as $uploadGroup) {
//                ProductUploadGroupFactory::createOne([
//                    'product' => $product,
//                    'upload_group' => $uploadGroup,
//                ]);
//            }
//        }
    }

    public function getDependencies(): array
    {
        return array(
            CategoryFixtures::class,
            PersonFixtures::class,
            InformationBlockFixtures::class,
            InformationBlockTypeFixtures::class,
            UploadFixtures::class,
            ProducerFixtures::class,
        );
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}