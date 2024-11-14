<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\CategoryFactory;
use Greendot\EshopBundle\Factory\Project\CategoryPersonFactory;
use Greendot\EshopBundle\Factory\Project\InformationBlockFactory;
use Greendot\EshopBundle\Factory\Project\PersonFactory;
use Greendot\EshopBundle\Factory\Project\PersonInformationBlockFactory;
use Greendot\EshopBundle\Factory\Project\PersonUploadGroupFactory;
use Greendot\EshopBundle\Factory\Project\UploadGroupFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PersonFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $persons = PersonFactory::createMany(10);

//        // PERSON
//        $persons = PersonFactory::createMany(10);
//
//        foreach ($persons as $person) {
//            // Assign person to 5-10 categories
//            $categories = CategoryFactory::randomRange(0, 10);
//            foreach ($categories as $category) {
//                // 5% chance to be a manager
//                $randomNumber = rand(1, 100);
//                $isManager = $randomNumber <= 5 ? 1 : 0;
//
//                CategoryPersonFactory::createOne([
//                    'category' => $category,
//                    'person' => $person,
//                    'sequence' => 1,
//                    'is_manager' => $isManager,
//                ]);
//            }
//
//            // Assign person to 1-4 upload groups
//            $uploadGroups = UploadGroupFactory::randomRange(1, 4);
//            foreach ($uploadGroups as $uploadGroup) {
//                PersonUploadGroupFactory::createOne([
//                    'person' => $person,
//                    'upload_group' => $uploadGroup,
//                ]);
//            }
//
//            // Assign person to 1-5 information blocks
//            $informationBlocks = InformationBlockFactory::randomRange(1, 5);
//            foreach ($informationBlocks as $informationBlock) {
//                PersonInformationBlockFactory::createOne([
//                    'person' => $person,
//                    'informationBlock' => $informationBlock,
//                    'sequence' => 1,
//                ]);
//            }
//        }
//
//
    }

    public function getDependencies(): array
    {
        return array(
            CategoryFixtures::class,
            UploadFixtures::class,
            InformationBlockFixtures::class,
        );
    }
    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}
