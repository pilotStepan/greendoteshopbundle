<?php

namespace Greendot\EshopBundle\Tests\Functional\Repository;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\CategoryProduct;
use Greendot\EshopBundle\Entity\Project\CategoryType;
use Greendot\EshopBundle\Entity\Project\HandlingPrice;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductProduct;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Repository\Project\CategoryProductRepository;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\ProductProductRepository;
use Greendot\EshopBundle\Tests\App\ApiTestCase;
use Greendot\EshopBundle\Tests\App\Factory\ProductFactory;
use Greendot\EshopBundle\Tests\App\Factory\ProductVariantFactory;
use Greendot\EshopBundle\Tests\App\Factory\PurchaseFactory;
use Greendot\EshopBundle\Tests\App\Factory\TransportationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Repository queries that PHPStan reported as broken (unknown fields, missing methods, wrong parameter types).
 */
class RepositoryQueryFixesTest extends ApiTestCase
{
    public function testDistinctParameterDataForVariants(): void
    {
        $group = (new ParameterGroup())->setName('Manufacturer');
        $this->getEntityManager()->persist($group);
        $a = ProductVariantFactory::createOne()->object();
        $b = ProductVariantFactory::createOne()->object();
        $this->parameter($group, $a, 'Acme');
        $this->parameter($group, $b, 'Acme');
        $this->parameter($group, ProductVariantFactory::createOne()->object(), 'Other');
        $this->getEntityManager()->flush();

        $repository = $this->service(ParameterRepository::class);
        $parameters = $repository->getDistinctDataOfParameterGroupForProductVariantArray($group, [$a->getId(), $b->getId()]);

        $this->assertSame(['Acme'], array_map(fn(Parameter $p) => $p->getData(), $parameters));
        $this->assertSame([], $repository->getDistinctDataOfParameterGroupForProductVariantArray(null, [$a->getId()]));
    }

    public function testRelatedCategoriesIncludeAndExcludeCategoryTypes(): void
    {
        // One product per category: the mapped "one main category per product" index is a plain unique index
        // on product_id once MariaDB drops its WHERE clause.
        $products = ProductFactory::createMany(3);
        $productIds = array_map(fn($product) => $product->getId(), $products);
        $shown = $this->categoryType('shown');
        $hidden = $this->categoryType('hidden');
        $root = $this->category('root', $shown, $products[0]->object());
        $kept = $this->category('kept', $shown, $products[1]->object());
        $excluded = $this->category('excluded', $hidden, $products[2]->object());
        $this->getEntityManager()->flush();

        $repository = $this->service(CategoryProductRepository::class);
        $ids = fn(?array $types, ?array $excludeTypes) => $this->sorted(
            $repository->getProductRelatedCategories($productIds, $root->getId(), true, $types, $excludeTypes),
        );

        $this->assertSame($this->sorted([$kept->getId(), $excluded->getId()]), $ids(null, null), 'the root category is excluded');
        $this->assertSame([$kept->getId()], $ids([$shown->getId()], null));
        $this->assertSame([$kept->getId()], $ids(null, [$hidden->getId()]), 'excluded types are left out, not kept');
    }

    public function testHandlingPriceByDateAcceptsADateTime(): void
    {
        $transportation = TransportationFactory::createOne()->object();
        $old = $this->handlingPrice($transportation, '2020-01-01', '2020-12-31', 50.0);
        $current = $this->handlingPrice($transportation, '2021-01-01', null, 80.0);
        $this->getEntityManager()->flush();

        $repository = $this->service(HandlingPriceRepository::class);

        $this->assertSame($old, $repository->GetByDate($transportation, new \DateTime('2020-06-01')));
        $this->assertSame($current, $repository->GetByDate($transportation, new \DateTimeImmutable('2022-06-01')));
    }

    public function testAddOnDiscountForAProductBoughtWithItsParent(): void
    {
        // Adding cart lines fires the cart listener, which reads the session.
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->service(RequestStack::class)->push($request);

        $parent = ProductVariantFactory::createOne()->object();
        $child = ProductVariantFactory::createOne()->object();
        $purchase = PurchaseFactory::createOne()->object();
        $this->line($purchase, $parent);
        $childLine = $this->line($purchase, $child);
        $link = (new ProductProduct())
            ->setParentProduct($parent->getProduct())
            ->setChildrenProduct($child->getProduct())
            ->setDiscount(10)
            ->setSequence(1);
        $this->getEntityManager()->persist($link);
        $this->getEntityManager()->flush();

        $repository = $this->service(ProductProductRepository::class);

        $this->assertSame($link, $repository->getForPurchaseProductVariant($childLine));
        $this->assertNull($repository->getForPurchaseProductVariant($this->line(PurchaseFactory::createOne()->object(), $child)));
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        $this->assertInstanceOf($id, $service);

        return $service;
    }

    private function parameter(ParameterGroup $group, ProductVariant $variant, string $data): void
    {
        $parameter = (new Parameter())->setParameterGroup($group)->setProductVariant($variant)->setData($data);
        $this->getEntityManager()->persist($parameter);
    }

    private function categoryType(string $name): CategoryType
    {
        $type = (new CategoryType())->setName($name)->setControllerName('category');
        $this->getEntityManager()->persist($type);

        return $type;
    }

    private function category(string $name, CategoryType $type, Product $product): Category
    {
        $category = (new Category())->setName($name)->setIsActive(true)->setCategoryType($type);
        $link = (new CategoryProduct())->setCategory($category)->setProduct($product)->setIsMainCategory(true);
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->persist($link);

        return $category;
    }

    private function handlingPrice(object $transportation, string $from, ?string $until, float $price): HandlingPrice
    {
        $handlingPrice = (new HandlingPrice())
            ->setTransportation($transportation)
            ->setPrice($price)
            ->setValidFrom(new \DateTime($from))
            ->setValidUntil($until !== null ? new \DateTime($until) : null);
        $this->getEntityManager()->persist($handlingPrice);

        return $handlingPrice;
    }

    private function line(Purchase $purchase, ProductVariant $variant): PurchaseProductVariant
    {
        $line = (new PurchaseProductVariant())->setPurchase($purchase)->setProductVariant($variant)->setAmount(1);
        $purchase->addProductVariant($line);
        $this->getEntityManager()->persist($line);

        return $line;
    }

    /**
     * @param list<int|null> $ids
     * @return list<int|null>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
