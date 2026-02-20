<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\ViewItemList\ViewItemList;
use Greendot\EshopBundle\DataLayer\Data\ViewItemList\ViewItemListItem;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ViewItemListFactory
{

    use FactoryUtilsTrait;

    public function __construct(
        private readonly ProductRepository   $productRepository,
        private readonly HttpKernelInterface $httpKernel,
        #[Autowire(param: 'greendot_eshop.global.absolute_url')]
        private readonly string              $absoluteUri,
        private readonly RequestStack        $requestStack,
    )
    {
    }

    public function create(Category $category, ?string $productsUri, ?array $productIds = null): ViewItemList
    {
        $products = $this->getProducts($productsUri, $productIds);
        $items = [];
        foreach ($products as $index => $product) {
            $items [] = $this->createListItem($product, $index);
        }

        return new ViewItemList(
            item_list_id: $category->getId(),
            item_list_name: $category->getName(),
            items: $items
        );
    }

    public function createListItem(Product $product, int $index): ViewItemListItem
    {
        $categories = [];
        $productCategory = $product?->getCategoryProducts()?->first()?->getCategory();
        if ($productCategory) {
            $categories[] = $this->getCategoryNameTreeUp($productCategory);
        }

        $calculatedPrices = $product->getCalculatedPrices() ?? [];


        return new ViewItemListItem(
            item_id: $product->getId(),
            item_name: $product->getName(),
            index: $index,
            priceVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceVat'),
            priceNoVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceNoVat'),
            quantity: 1,
            item_brand: $product?->getProducer()?->getName() ?? 'Unknown',
            categories: $categories,
        );
    }

    /**
     * @param string|null $productsUri
     * @param array|null $productIds
     * @return Product[]
     */
    private function getProducts(?string $productsUri, ?array $productIds = null): array
    {
        if ($productsUri) {
            $productIds = $this->doSubRequest($productsUri);
            $productIds = array_column($productIds, 'id');
        }

        return $this->productRepository->createQueryBuilder('product')
            ->andWhere('product.id in (:productIds)')
            ->setParameter('productIds', $productIds)
            ->getQuery()->getResult();

    }

    private function doSubRequest(string $productsUri): array
    {
        $mainRequest = $this->requestStack->getMainRequest();

        $subRequest = Request::create($this->absoluteUri . $productsUri, 'GET');
        if ($mainRequest && $mainRequest->hasSession()) {
            $subRequest->setSession($mainRequest->getSession());
        }

        try {
            $response = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        } catch (\Exception $exception) {
            return [];
        }
        $response = json_decode($response->getContent(), true);
        if (isset($response['member'])) {
            return $response['member'];
        }
        return $response;
    }
}