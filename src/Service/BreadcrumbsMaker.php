<?php

namespace Greendot\EshopBundle\Service;

use Exception;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Enum\SpecialCategoryEnum;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use InvalidArgumentException;

class BreadcrumbsMaker
{

    public function __construct(
        private CategoryRepository  $categoryRepository,
        private CategoryInfoGetter  $categoryInfoGetter,
        private ProductInfoGetter   $productInfoGetter,
    ) {}


    public function makeEntityBreadCrumbsArray($entity) : array
    {
        return match (true) {
            $entity instanceof Category => $this->makeCategoryBreadCrumbsArray($entity),
            $entity instanceof Product  => $this->makeProductBreadCrumbsArray($entity),
            $entity instanceof Producer => $this->makeProducerBreadCrumbsArray($entity),
            default => throw new InvalidArgumentException(sprintf(
                "Breadcrumbs cannot be generated for class '%s'.", 
                $entity::class
            )),
        };

    }
    
    public function makeCategoryBreadCrumbsArray(Category $category) : array
    {
        return $this->categoryInfoGetter->getCategoryBreadCrumbsArray($category);
    }
    
    public function makeProductBreadCrumbsArray(Product $product) : array
    {
        return $this->productInfoGetter->getProductBreadCrumbsArray($product);
    }

    public function makeProducerBreadCrumbsArray(Producer $producer) : array
    {
        
        $bredCrumbsArray = [$producer];
        
        
        $producersCategory = $this->categoryRepository->findOneBy(["specialCategoryCode" => SpecialCategoryEnum::PRODUCERS_LANDING]);
        if ($producersCategory)
        {
            
            $bredCrumbsArray array_merge($bredCrumbsArray, )
        }



        return $bredCrumbsArray;
    }

    // public function makeCommentBreadCrumbsArray(Producer $producer) : array
    // {
    //     // todo
    // }


}