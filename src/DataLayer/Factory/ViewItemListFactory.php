<?php

namespace Greendot\EshopBundle\DataLayer\Factory;

use Greendot\EshopBundle\DataLayer\Data\DataLayerItem;
use Greendot\EshopBundle\DataLayer\Data\ViewItemList\ViewItemList;

use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ViewItemListFactory
{

    use FactoryUtilsTrait;

    public function __construct(
        private readonly ProductRepository       $productRepository,
        private readonly HttpKernelInterface     $httpKernel,
        #[Autowire(param: 'greendot_eshop.global.absolute_url')]
        private readonly string                  $absoluteUri,
        private readonly RequestStack            $requestStack,
        private readonly PriceRepository         $priceRepository,
        private readonly CalculatedPricesService $calculatedPricesService,
        private readonly CurrencyManager         $currencyManager,
        private readonly PriceUtils              $priceUtils,
        private readonly DataLayerItemFactory    $dataLayerItemFactory,
    )
    {
    }

    public function create(Category $category, ?string $productsUri, ?array $productIds = null): ViewItemList
    {
        $products = $this->getProducts($productsUri, $productIds);
        $items = [];
        $valueVat = 0;
        $valueNoVat = 0;
        foreach ($products as $index => $product) {
            $item = $this->createListItem($product, $index);
            $valueVat += $item->priceVat;
            $valueNoVat += $item->priceNoVat;
            $items [] = $item;
        }

        return new ViewItemList(
            item_list_id: $category->getId(),
            item_list_name: $category->getName(),
            valueVat: $valueVat,
            valueNoVat: $valueNoVat,
            items: $items
        );
    }

    public function createListItem(Product $product, int $index): DataLayerItem
    {
        $calculatedPrices = $product->getCalculatedPrices() ?? [];

        return $this->dataLayerItemFactory->createFromProduct(
            product: $product,
            priceVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceVat'),
            priceNoVat: $this->getFromCalculatedPricesSafe($calculatedPrices, 'priceNoVat'),
            index: $index,
        );
    }

    /**
     * @param string|null $productsUri
     * @param array|null $productIds
     * @return Product[]
     */
    protected function getProducts(?string $productsUri, ?array $productIds = null): array
    {
        if ($productsUri) {
            $productIds = $this->doSubRequest($productsUri);
            $productIds = array_column($productIds, 'id');
        }

        $products = $this->productRepository->createQueryBuilder('product')
            ->andWhere('product.id in (:productIds)')
            ->setParameter('productIds', $productIds)
            ->getQuery()->getResult();

        $cheapestPriceMap = $this->priceRepository->findCheapestPricesForProducts($productIds);

        $currency = $this->currencyManager->get();
        $conversionRate = $this->priceUtils->getConversionRate($currency);
        $context = new ProductVariantPriceContext(
            currencyOrConversionRate: $conversionRate
        );


        foreach ($products as $product){
            $this->calculatedPricesService->makeCalculatedPricesForProduct(
                product: $product, context: $context, cheapestPrice: $cheapestPriceMap[$product->getId()]);
        }

        return $products;

    }

    protected function doSubRequest(string $productsUri): array
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