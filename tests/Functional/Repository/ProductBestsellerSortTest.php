<?php

namespace Greendot\EshopBundle\Tests\Functional\Repository;

use Greendot\EshopBundle\Entity\Project\Availability;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ProductFactory;
use Greendot\EshopBundle\Tests\App\Factory\ProductVariantFactory;

/**
 * orderBy.id = 'bestseller' in the product listing filter (GET /products/filter).
 */
class ProductBestsellerSortTest extends ApiTestCase
{
    private const BESTSELLER = ['orderBy' => ['id' => 'bestseller', 'direction' => 'DESC']];

    public function testOrdersBySoldAmountThenSequence(): void
    {
        $unsold = $this->product(null, 10);
        $top = $this->product(50, 1);
        $tiedLowSequence = $this->product(5, 1);
        $tiedHighSequence = $this->product(5, 9);
        $zero = $this->product(0, 20);
        $this->getEntityManager()->flush();

        // sold DESC (NULL as 0), then sequence DESC as in the default listing, then id DESC
        $this->assertSame(
            $this->ids($top, $tiedHighSequence, $tiedLowSequence, $zero, $unsold),
            $this->filter(self::BESTSELLER),
        );
    }

    public function testInsideACategoryTiesFollowTheCategoryOrder(): void
    {
        $category = (new Category())->setName('mats')->setIsActive(true);
        $this->getEntityManager()->persist($category);
        $second = $this->product(5, 1, $category, 2);
        $first = $this->product(5, 1, $category, 1);
        $top = $this->product(8, 1, $category, 3);
        $this->product(100, 1); // outside the category
        $this->getEntityManager()->flush();

        $this->assertSame(
            $this->ids($top, $first, $second),
            $this->filter(self::BESTSELLER + ['categoryId' => $category->getId(), 'crawlCategoryUp' => false]),
        );
    }

    public function testStockOnlyKeepsAvailableProductsFirst(): void
    {
        $inStock = $this->availability(1);
        $onOrder = $this->availability(3);
        $bestsellerOnOrder = $this->product(100, 1, availability: $onOrder);
        $available = $this->product(2, 1, availability: $inStock);
        $moreAvailable = $this->product(7, 1, availability: $inStock);
        $this->getEntityManager()->flush();

        $this->assertSame(
            $this->ids($moreAvailable, $available, $bestsellerOnOrder),
            $this->filter(self::BESTSELLER + ['isStockOnly' => true]),
        );
    }

    public function testAscendingDirectionListsTheLeastSoldFirst(): void
    {
        $many = $this->product(9, 1);
        $few = $this->product(1, 1);
        $this->getEntityManager()->flush();

        $this->assertSame($this->ids($few, $many), $this->filter(['orderBy' => ['id' => 'bestseller', 'direction' => 'ASC']]));
    }

    public function testCountIsUnaffected(): void
    {
        $this->product(3, 1);
        $this->product(null, 1);
        $this->getEntityManager()->flush();

        $count = $this->repository()->mainProductsFilter(self::BESTSELLER, true)->getQuery()->getSingleScalarResult();

        $this->assertSame(2, (int)$count);
    }

    private function product(?int $sold, int $sequence, ?Category $category = null, ?int $categorySequence = null, ?Availability $availability = null): Product
    {
        $product = ProductFactory::createOne()->object();
        $product->setSoldAmount($sold)->setSequence($sequence);

        if ($category !== null) {
            $link = (new CategoryProduct())->setCategory($category)->setProduct($product)->setSequence($categorySequence);
            $this->getEntityManager()->persist($link);
        }

        if ($availability !== null) {
            ProductVariantFactory::createOne(['product' => $product, 'availability' => $availability, 'isActive' => true]);
        }

        return $product;
    }

    private function availability(int $sequence): Availability
    {
        $availability = (new Availability())
            ->setName('availability ' . $sequence)
            ->setDescription('')
            ->setClass('')
            ->setIsPurchasable(true)
            ->setSequence($sequence);
        $this->getEntityManager()->persist($availability);

        return $availability;
    }

    /** @return int[] */
    private function filter(array $filters): array
    {
        return array_map('intval', $this->repository()->mainProductsFilter($filters)->getQuery()->getSingleColumnResult());
    }

    /** @return int[] */
    private function ids(Product ...$products): array
    {
        return array_map(static fn(Product $product) => $product->getId(), $products);
    }

    private function repository(): ProductRepository
    {
        $repository = self::getContainer()->get(ProductRepository::class);
        $this->assertInstanceOf(ProductRepository::class, $repository);

        return $repository;
    }
}
