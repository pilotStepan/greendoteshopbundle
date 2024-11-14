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
        $defaultCategories = $this->getDefaultCategories();

        foreach ($defaultCategories as $id => $data) {
            $defaultCategory = new Category();
            $defaultCategory->setName($data['name']);
            $defaultCategory->setHtml($data['html']);
            $defaultCategory->setSlug($data['slug']);
            $defaultCategory->setIsActive($data['is_active']);
            $defaultCategory->setState($data['state']);
            $defaultCategory->setIsIndexable($data['is_indexable']);
            $this->entityManager->persist($defaultCategory);
        }

        CategoryFactory::createMany(5, [
            'category_type' => $this->entityManager->getRepository(CategoryType::class)->findOneBy(['name' => 'Stránka']),
        ]);


        $categories = CategoryFactory::createMany(4);
        foreach ($categories as $category){
            $category->addMenuType($this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Sekundární menu']));
            $this->entityManager->persist($category->object());
        }

        $categories = CategoryFactory::createMany(4);
        foreach ($categories as $category){
            $category->addMenuType($this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Horní menu']));
            $this->entityManager->persist($category->object());
        }


        $categories = CategoryFactory::createMany(7, [
            'category_type' => $this->entityManager->getRepository(CategoryType::class)->findOneBy(['name' => 'Category']),
        ]);
        foreach ($categories as $category){
            $category->addMenuType($this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Hlavní menu']));
            $category->addSubMenuType($this->entityManager->getRepository(SubMenuType::class)->findOneBy(['name' => 'E-shop']));
            $this->entityManager->persist($category->object());
        }

        $subCategories = [];

        foreach ($categories as $category) {
            $random = rand(0,2) * 4;
            for ($i = 0; $i < $random; $i++) {
                $subCategory = CategoryFactory::createOne([
                    'category_type' => $this->entityManager->getRepository(CategoryType::class)->findOneBy(['name' => 'SubCategory']),
                ]);
                $subCategories[] = $subCategory;

                $subCategory->addMenuType($this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Hlavní menu']));
                $this->entityManager->persist($subCategory->object());

                CategoryCategoryFactory::createOne([
                    'category_sub' => $subCategory,
                    'category_super' => $category,
                ]);
            }
        }

        // Create j sub-sub-categories for each sub-category
        foreach ($subCategories as $subCategory) {
            for ($j = 0; $j < rand(0,2); $j++) {
                $subSubCategory = CategoryFactory::createOne([
                    'category_type' => $this->entityManager->getRepository(CategoryType::class)->findOneBy(['name' => 'SubCategory']),
                ]);

                $subSubCategory->addMenuType($this->entityManager->getRepository(MenuType::class)->findOneBy(['name' => 'Hlavní menu']));
                $this->entityManager->persist($subSubCategory->object());

                CategoryCategoryFactory::createOne([
                    'category_sub' => $subSubCategory,
                    'category_super' => $subCategory,
                ]);
            }
        }

        // Connect categories with upload groups
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

    private function slugify(string $name): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

    private function getDefaultCategories(): array
    {
        return [
            1 => [
                $name = 'Homepage',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 1,
            ],
            2 => [
                $name = 'Novinky',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            3 => [
                $name = 'Category 3',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            4 => [
                $name = 'Category 4',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            5 => [
                $name = 'Category 5',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            6 => [
                $name = 'Category 6',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            7 => [
                $name = 'Category 7',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            8 => [
                $name = 'Category 8',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            9 => [
                $name = 'Category 9',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
            10 => [
                $name = 'Category 10',
                'name' => $name,
                'html' => "<h1>$name</h1>",
                'slug' => $this->slugify($name),
                'is_active' => 1,
                'state' => 'draft',
                'is_indexable' => 1,
                'is_menu' => 0,
            ],
        ];
    }
}