<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Interface\PagableInterface;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Dto\BreadCrumb;
use Greendot\EshopBundle\Enum\SpecialCategoryEnum;
use Greendot\EshopBundle\Repository\Project\CategoryProductRepository;
use InvalidArgumentException;

class BreadcrumbsMaker
{


    public function __construct(
        private CategoryRepository          $categoryRepository,
        private CategoryProductRepository   $categoryProductRepository,
        private CategoryInfoGetter          $categoryInfoGetter,
        private UrlGeneratorInterface       $router,
    ) {}


    public function makeEntityBreadCrumbsArray(PagableInterface $entity) : array
    {
        return match (true) {
            $entity instanceof Category => $this->makeCategoryBreadCrumbsArray($entity),
            $entity instanceof Product  => $this->makeProductBreadCrumbsArray($entity),
            $entity instanceof Producer => $this->makeProducerBreadCrumbsArray($entity),
            $entity instanceof Comment  => $this->makeCommentBreadCrumbsArray($entity), 
            default => throw new InvalidArgumentException(sprintf(
                "Breadcrumbs cannot be generated for class '%s'.", 
                $entity::class
            )),
        };

    }
    
    public function makeCategoryBreadCrumbsArray(Category $category) : array
    {        
        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($category);
        
        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }
    
    public function makeProductBreadCrumbsArray(Product $product) : array
    {
        $mainCategory = $this->categoryProductRepository->getMainCategoryForProduct($product->getId())?->getCategory() ?? $product->getCategoryProducts()->first()->getCategory();
        
        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($mainCategory);
        array_push($breadcrumbsArray, $product);
        
        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    public function makeProducerBreadCrumbsArray(Producer $producer) : array
    {
        $producersCategory = $this->categoryRepository->findOneBy(["specialCategoryCode" => SpecialCategoryEnum::PRODUCERS_LANDING]);  

        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($producersCategory);
        array_push($breadcrumbsArray, $producer);
        
        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    public function makeCommentBreadCrumbsArray(Comment $comment) : array
    {
        $advisoryCategory = $this->categoryRepository->findOneBy(["specialCategoryCode" => SpecialCategoryEnum::ADVISORY]);        

        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($advisoryCategory);
        array_push($breadcrumbsArray, $comment);
        
        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    public function makeEntityBreadCrumb(PagableInterface $entity) 
    {
        return new BreadCrumb(
            name: $entity->getName(),
            link: $this->getEntityUrl($entity),
        );
    }

    public function mapEntitiesToBreadCrumbArray(array $entities) : array
    {
        return array_map(fn($e) => $this->makeEntityBreadCrumb($e), $entities);
    }

    public function getEntityUrl(PagableInterface $entity) : string
    {
        return match (true) {
            $entity instanceof Category => $this->getCategoryUrl($entity),
            $entity instanceof Product  => $this->getProductUrl($entity),
            $entity instanceof Producer => $this->getProducerUrl($entity),
            $entity instanceof Comment  => $this->getCommentUrl($entity), 
            default => throw new InvalidArgumentException(sprintf(
                "Url cannot be generated for class '%s'.", 
                $entity::class
            )),
        };
    }

    public function getCategoryUrl(Category $category) : string
    {
        $route = $category->getCategoryType()->getControllerName();
        return $this->router->generate($route, [
            'slug' => $category->getSlug()
        ]);
    }

    public function getProductUrl(Product $product) : string
    {
        return $this->router->generate('shop_product', [
            'slug' => $product->getSlug()
        ]);
    }
    
    public function getProducerUrl(Producer $producer) : string
    {
        return $this->router->generate('shop_producer_products', [
            'slug' => $producer->getSlug()
        ]);
    }
    
    public function getCommentUrl(Comment $comment) : string
    {
        return $this->router->generate('app_comment_detail', [
            'slug' => $comment->getSlug()
        ]);
    }

}