<?php
namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryCategory;
use Greendot\EshopBundle\Entity\Project\MenuType;
use Greendot\EshopBundle\Entity\Project\SubMenuType;
use Greendot\EshopBundle\Repository\Project\CategoryCategoryRepository;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Exception;

class CategoryInfoGetter
{
    private CategoryCategoryRepository $categoryCategoryRepository;
    public function __construct(CategoryCategoryRepository $categoryCategoryRepository, private readonly CategoryRepository $categoryRepository)
    {
        $this->categoryCategoryRepository = $categoryCategoryRepository;
    }

    public function getCategoryBreadCrumbsArray(Category $category): array
    {
        $while = true;
        $iteratedCategory = $category;
        $returnArray = [];
        do{
            $returnArray []= $iteratedCategory;
            if ($iteratedCategory->getCategorySubCategories()->count() > 0){
                $iteratedCategory = $iteratedCategory->getCategorySubCategories()->first()->getCategorySuper();
            }else{
                $while = false;
            }
        }while($while == true);

        return  array_reverse($returnArray);
    }

    //returns all categories under category even indirectly, the given category is also included in returned array
    public function getAllSubCategories($category): array
    {
        $allSubCategories = [$category];
        $superCategories = $this->categoryCategoryRepository->findConnectionsByCategorySuper($category->getId());
        if (count($superCategories) > 0){
            do{
                $underCategories = [];
                foreach ($superCategories as $subCategories){
                    array_push($allSubCategories, $subCategories->getCategorySub());
                    $underCategories = array_merge($underCategories, $this->categoryCategoryRepository->findConnectionsByCategorySuper( $subCategories->getCategorySub()->getId() ) );
                }
                $superCategories = $underCategories;
            }while($superCategories);
            return $allSubCategories;
        } else {
            return $allSubCategories;
        }
    }


    public function getMostSuperCategoryOfCategory(Category $category): Category
    {
        while (true){
            $subCategoryCategories = $category->getCategorySubCategories();
            if ($subCategoryCategories->count() == 0){
                break;
            }

            $category = $subCategoryCategories->first()->getCategorySuper();

        }

        return $category;

    }

    public function findSubMenuCategories(Category $category, SubMenuType $menuType)
    {
        return $this->categoryRepository->findSubMenuCategories($category, $menuType);
    }

    /**
     * @throws Exception
     */
    public function getCategoriesForEntity($entity, $onlyActive = true, $categoryTypeID = null){
        return $this->categoryRepository->getCategoriesForEntity($entity, $onlyActive, $categoryTypeID);
    }



}