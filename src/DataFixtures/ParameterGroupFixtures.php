<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\ParameterGroupType;
use Greendot\EshopBundle\Factory\Project\ParameterGroupFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Greendot\EshopBundle\Factory\Project\ParameterGroupFilterTypeFactory;

class ParameterGroupFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{

    public function load(ObjectManager $manager): void
    {
        $defaultParameterGroups = $this->getDefaultParameterGroups();

        foreach ($defaultParameterGroups as $defaultParameterGroup) {
            ParameterGroupFactory::createOne($defaultParameterGroup);
        }

        ParameterGroupFactory::createMany(10);
    }

    public function getDependencies(): array
    {
        return array(
            ParameterGroupTypeFixtures::class,
            ParameterGroupFilterTypeFixtures::class,
        );
    }

    public static function getGroups(): array
    {
        return ['dynamic'];
    }

    private function getDefaultParameterGroups(): array
    {
        $filterType = ParameterGroupFilterTypeFactory::random();
        $type = $this->entityManager->getRepository(ParameterGroupType::class)->findOneBy(['name' => 'Produktové parametry']);

        return [
            1 => [
                'type' => $type,
                'isProductParameter' => 1,
                'name' => 'Barva',
                'unit' => null,
//                'isFilter' => 1,
                'parameterGroupFilterType' => $filterType,
            ],
            2 => [
                'type' => $type,
                'isProductParameter' => 1,
                'name' => 'Velikost',
                'unit' => null,
//                'isFilter' => 1,
                'parameterGroupFilterType' => $filterType,
            ],
        ];
    }
}