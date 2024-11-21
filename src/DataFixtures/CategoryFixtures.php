<?php

namespace Greendot\EshopBundle\DataFixtures;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Greendot\EshopBundle\Factory\Project\CategoryCategoryFactory;
use Greendot\EshopBundle\Factory\Project\CategoryFactory;
use Greendot\EshopBundle\Factory\Project\CategoryUploadGroupFactory;
use Greendot\EshopBundle\Factory\Project\UploadGroupFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->createDefaultCategories();

        $this->createCategoriesWithMenuType('Stránka', 'Sekundární menu', 5);
        $this->createCategoriesWithMenuType('Stránka', 'Horní menu', 4);
        $categories = $this->createCategoriesWithMenuType('Category', 'Hlavní menu', 7, 'E-shop');

        $subCategories = $this->createNestedCategories($categories, 3);
        $subSubCategories = $this->createNestedCategories($subCategories, 2);
        $subSubSubCategories = $this->createNestedCategories($subSubCategories, 1);

        $this->connectCategoriesWithUploadGroups();

        $this->entityManager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            AppFixtures::class,
            UploadFixtures::class,
            CategoryTypeFixtures::class,
            MenuTypeFixtures::class,
            SubMenuTypeFixtures::class,
        );
    }

    public static function getGroups(): array
    {
        return ['dynamic'];
    }

    private function createNestedCategories(array $superCategories, int $iterationMultiplier): array
    {
        $nestedCategories = [];

        foreach ($superCategories as $superCategory) {
            $iterations = rand(0, 3) * $iterationMultiplier;
            for ($i = 0; $i < $iterations; $i++) {
                $nestedCategory = CategoryFactory::createOne([
                    'category_type' => $this->entityManager->getRepository(CategoryType::class)->findOneBy(['name' => 'SubCategory']),
                ]);

                $mainMenuType = $this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Hlavní menu']);
                $nestedCategory->addMenuType($mainMenuType);
                $this->entityManager->persist($nestedCategory->object());

                CategoryCategoryFactory::createOne([
                    'category_sub' => $nestedCategory,
                    'category_super' => $superCategory,
                ]);

                $nestedCategories[] = $nestedCategory;
            }
        }

        return $nestedCategories;
    }

    private function createCategoriesWithMenuType(string $categoryTypeName, string $menuTypeName, int $count, string $subMenuTypeName = null): array
    {
        $categoryType = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['name' => $categoryTypeName]);
        $menuType = $this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => $menuTypeName]);
        $subMenuType = $subMenuTypeName ? $this->entityManager->getRepository(SubMenuType::class)->findOneBy(['name' => $subMenuTypeName]) : null;

        $categories = CategoryFactory::createMany($count, ['category_type' => $categoryType]);

        foreach ($categories as $category) {
            $category->addMenuType($menuType);
            if ($subMenuType) {
                $category->addSubMenuType($subMenuType);
            }
            $this->entityManager->persist($category->object());
        }

        return $categories;
    }

    private function createDefaultCategories(): void
    {
        $categories = [
            ['name' => 'Homepage', 'slug' => '', 'is_active' => 1, 'is_menu' => 1],
            ['name' => 'Blog', 'slug' => null, 'is_active' => 1, 'is_menu' => 0],
        ];

        for ($i = 3; $i <= 10; $i++) {
            $categories[] = [
                'name' => "Placeholder $i",
                'slug' => null,
                'is_active' => 0,
                'is_menu' => 0,
            ];
        }

        foreach ($categories as $categoryData) {
            $name = $categoryData['name'];
            $category = new Category();
            $category->setName($name);
            $category->setHtml("<h1>$name</h1>");
            $category->setSlug($categoryData['slug'] ?? $this->slugify($name));
            $category->setIsActive($categoryData['is_active']);
            $category->setState('draft');
            $category->setIsIndexable(1);
            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();
    }


    private function slugify(string $name): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

    private function connectCategoriesWithUploadGroups(): void
    {
        $categories = CategoryFactory::all();
        foreach ($categories as $category) {
            $uploadGroups = UploadGroupFactory::randomRange(1, 4);
            foreach ($uploadGroups as $uploadGroup) {
                CategoryUploadGroupFactory::createOne([
                    'category' => $category,
                    'upload_group' => $uploadGroup,
                ]);
            }
        }
    }
}