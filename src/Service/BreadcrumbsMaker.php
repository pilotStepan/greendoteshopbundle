<?php

namespace Greendot\EshopBundle\Service;

use InvalidArgumentException;
use Greendot\EshopBundle\Dto\BreadCrumb;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Enum\SpecialCategoryEnum;
use Greendot\EshopBundle\Entity\Interface\PageableInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\CategoryProductRepository;

class BreadcrumbsMaker
{
    public function __construct(
        private CategoryRepository        $categoryRepository,
        private CategoryProductRepository $categoryProductRepository,
        private CategoryInfoGetter        $categoryInfoGetter,
        private UrlGeneratorInterface     $router,
    ) {}


    public function makeEntityBreadCrumbsArray(PageableInterface $entity): array
    {
        return match (true) {
            $entity instanceof Category => $this->makeCategoryBreadCrumbsArray($entity),
            $entity instanceof Product  => $this->makeProductBreadCrumbsArray($entity),
            $entity instanceof Producer => $this->makeProducerBreadCrumbsArray($entity),
            $entity instanceof Comment  => $this->makeCommentBreadCrumbsArray($entity),
            default                     => throw new InvalidArgumentException(sprintf(
                "Breadcrumbs cannot be generated for class '%s'.",
                $entity::class,
            )),
        };

    }

    private function makeCategoryBreadCrumbsArray(Category $category): array
    {
        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($category);

        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    private function makeProductBreadCrumbsArray(Product $product): array
    {
        $mainCategory = $this->categoryProductRepository->getMainCategoryForProduct($product->getId())?->getCategory() ?? $product->getCategoryProducts()->first()->getCategory();

        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($mainCategory);
        $breadcrumbsArray[] = $product;

        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    private function makeProducerBreadCrumbsArray(Producer $producer): array
    {
        $producersCategory = $this->categoryRepository->findOneBy(["specialCategoryCode" => SpecialCategoryEnum::PRODUCERS_LANDING]);

        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($producersCategory);
        $breadcrumbsArray[] = $producer;

        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    private function makeCommentBreadCrumbsArray(Comment $comment): array
    {
        $advisoryCategory = $this->categoryRepository->findOneBy(["specialCategoryCode" => SpecialCategoryEnum::ADVISORY]);

        $breadcrumbsArray = $this->categoryInfoGetter->getCategoryBreadCrumbsArray($advisoryCategory);
        $breadcrumbsArray[] = $comment;

        return $this->mapEntitiesToBreadCrumbArray($breadcrumbsArray);
    }

    /** @var PageableInterface[] $entities */
    private function mapEntitiesToBreadCrumbArray(array $entities): array
    {
        return array_map(fn($e) => $this->makeEntityBreadCrumb($e), $entities);
    }

    private function makeEntityBreadCrumb(PageableInterface $entity): BreadCrumb
    {
        return new BreadCrumb(
            name: $entity->getTitle(),
            link: $this->router->generate($entity->getControllerName(), [
                'slug' => $entity->getSlug(),
            ]),
        );
    }
}