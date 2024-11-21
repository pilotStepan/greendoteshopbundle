<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Factory\Project\ParameterFactory;
use Greendot\EshopBundle\Factory\Project\ProductFactory;
use Greendot\EshopBundle\Factory\Project\ProductParameterGroupFactory;
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
        $faker = FakerFactory::create();
        $products = ProductFactory::all();
        $colors = ['#FF5733', '#33FF57', '#3357FF', '#F3FF33', '#FF33F3', '#33FFF3'];
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];

        $availableGroups = [
            // ensure that ParameterGroup's repository is ParameterGroupRepository if something goes wrong
            'Barva' => $manager->getRepository(ParameterGroup::class)->findOneBy(['name' => 'Barva']),
            'Velikost' => $manager->getRepository(ParameterGroup::class)->findOneBy(['name' => 'Velikost']),
        ];

        foreach ($products as $product) {

            $numVariants = rand(1, 3);
            $productVariants = [];
            $usedColors = [];
            $usedSizes = [];


            for ($i = 0; $i < $numVariants; $i++) {
                $variant = ProductVariantFactory::createOne([
                    'name' => $product->getName() . '- ' . $faker->word(),
                    'product' => $product,
                    'upload' => $product->getUpload(),
                ]);

                $productVariants[] = $variant;
            }

            if (count($productVariants) > 1) {
                $parameterGroups = [];

                foreach ($availableGroups as $name => $group) {
                    if (rand(0, 1)) {
                        $parameterGroups[$name] = $group;
                    }
                }

                foreach ($parameterGroups as $groupName => $group) {
                    ProductParameterGroupFactory::createOne([
                        'product' => $product,
                        'parameterGroup' => $group,
                        'isVariant' => true,
                    ]);

                    foreach ($productVariants as $variant) {
                        if ($groupName === 'Barva') {
                            $data = $this->getUniqueData($colors, $usedColors);
                            if ($data !== null) {
                                $usedColors[] = $data;
                                ParameterFactory::createOne([
                                    'parameterGroup' => $group,
                                    'data' => $data,
                                    'productVariant' => $variant,
                                ]);
                            }
                        } elseif ($groupName === 'Velikost') {
                            $data = $this->getUniqueData($sizes, $usedSizes);
                            if ($data !== null) {
                                $usedSizes[] = $data;
                                ParameterFactory::createOne([
                                    'parameterGroup' => $group,
                                    'data' => $data,
                                    'productVariant' => $variant,
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    private function getUniqueData(array $pool, array $used): ?string
    {
        $available = array_diff($pool, $used);
        return $available ? array_shift($available) : null;
    }

    public function getDependencies(): array
    {
        return [
            ProductFixtures::class,
            UploadFixtures::class,
            ParameterGroupFixtures::class,
            AvailabilityFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['dynamic'];
    }
}