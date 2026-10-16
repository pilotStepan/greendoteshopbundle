<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Greendot\EshopBundle\Dto\ProductVariantPriceContext;
use Greendot\EshopBundle\EventSubscriber\ProductEventListener;
use Greendot\EshopBundle\Repository\Project\PriceRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Service\CurrencyManager;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;
use Greendot\EshopBundle\Service\Price\PriceUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Prices for product lists whose data comes from outside the shop DB (e.g. Algolia search hits),
 * fetched by the frontend in a separate request after the list itself has been rendered.
 */
class ProductPriceController extends AbstractController
{
    public const MAX_PRODUCTS = 100;

    public function __construct(
        private readonly ProductRepository       $productRepository,
        private readonly PriceRepository         $priceRepository,
        private readonly CalculatedPricesService $calculatedPricesService,
        private readonly CurrencyManager         $currencyManager,
        private readonly PriceUtils              $priceUtils,
        private readonly ListenerManager         $listenerManager,
    ) {}

    #[CustomApiEndpoint]
    #[Route('/api/product-prices', name: 'api_product_prices', options: ['expose' => true], methods: ['GET'])]
    public function productPrices(Request $request): JsonResponse
    {
        $productIds = $this->parseIds((string)$request->query->get('ids', ''));
        if (!$productIds) {
            return new JsonResponse(new \stdClass());
        }

        // availability/parameters computed on postLoad are not needed here
        $this->listenerManager->disableAll([ProductEventListener::class]);

        // loaded through the repository so Doctrine filters (active products etc.) apply
        $products = $this->productRepository->findBy(['id' => $productIds]);
        $cheapestPriceMap = $this->priceRepository->findCheapestPricesForProducts($productIds);

        $context = new ProductVariantPriceContext(
            currencyOrConversionRate: $this->priceUtils->getConversionRate($this->currencyManager->get()),
        );

        $prices = [];
        foreach ($products as $product) {
            $cheapestPrice = $cheapestPriceMap[$product->getId()] ?? null;
            if (!$cheapestPrice) {
                continue;
            }

            $this->calculatedPricesService->makeCalculatedPricesForProduct($product, $context, $cheapestPrice);
            if ($product->getCalculatedPrices()) {
                $prices[$product->getId()] = $product->getCalculatedPrices();
            }
        }

        // object cast keeps the response a JSON object even when empty
        return new JsonResponse((object)$prices);
    }

    /**
     * @return int[]
     */
    private function parseIds(string $raw): array
    {
        $ids = array_filter(
            array_map('trim', explode(',', $raw)),
            static fn(string $id) => ctype_digit($id) && (int)$id > 0,
        );

        return array_slice(array_values(array_unique(array_map('intval', $ids))), 0, self::MAX_PRODUCTS);
    }
}
