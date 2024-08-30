<?php

namespace Greendot\EshopBundle\Service;

use Algolia\SearchBundle\SearchService;
use App\Api\SearchProductResultApiModel;
use App\Entity\AlgoliaProduct;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class AlgoliaSearch
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface    $serializer,
        private readonly ProductInfoGetter      $productInfoGetter,
        protected SearchService                 $searchService,
        private readonly CurrencyRepository     $currencyRepository,
        private readonly CategoryRepository     $categoryRepository
    )
    {
    }

    public function search(string $query, Currency|bool|null $currency, int $hits = 12, int $page = 0, $isJson = false, array $filters = []): JsonResponse|array
    {
        $allFilters = [];
        foreach ($filters as $key => $value) {
            if ($value) {
                foreach ($value as $i => $item) {
                    $filter = $key . ":" . $item;
                    $allFilters[$key] [] = $filter;
                }

            }
        }
        $allFilters = array_values($allFilters);

        if (!($currency instanceof Currency)) {
            $currency = $this->currencyRepository->findOneBy(['isDefault' => 1]);
        }

        $query = str_replace('.', '', $query);
        $JsonResults = $this->searchService->rawSearch(/*$entityManager, */ AlgoliaProduct::class, $query, [
            'page' => $page,
            'hitsPerPage' => $hits,
            'facets' => [
                'categories.id',
                'manufacturer'
            ],
            'facetFilters' => $allFilters,
        ]);
        $hits = $this->searchService->count(AlgoliaProduct::class, $query);
        $result = [];
        if (isset($JsonResults['hits'])) {
            foreach ($JsonResults['hits'] as $hit) {

                $object = $hit['objectID'];
                $object = explode("::", $object);
                $object = $this->entityManager->getRepository($object[0])->find($object[1]);
                $apiEntity = new SearchProductResultApiModel();
                if (isset($hit["_highlightResult"])) {
                    $fully = false;
                    $level = 0;
                    $value = "";
                    foreach ($hit['_highlightResult'] as $highlight) {
                        if (isset($highlight['matchLevel']) and $highlight['matchLevel'] != "none" and isset($highlight["value"]) and isset($highlight["matchLevel"]) and isset($highlight["fullyHighlighted"])) {
                            $currentLevel = $highlight["matchLevel"] == "full" ? 2 : 1;
                            if ($fully < $highlight["fullyHighlighted"] or $currentLevel > $level) {
                                $fully = $highlight['fullyHighlighted'];
                                $level = $currentLevel;
                                $value = $highlight['value'];
                            }
                        }
                    }
                    $apiEntity->setHighlightedResult($value);
                    $apiEntity->setIsFullMatch($fully);
                }


                if ($object instanceof ProductVariant) {
                    if ($object != null) {
                        $apiEntity->parseProduct($object->getProduct(), $this->serializer);
                        $apiEntity->setProductVariant($object);
                        $apiEntity->setPriceFrom($this->productInfoGetter->getProductPriceString($object->getProduct(), $currency));
                        $result[] = $apiEntity;
                    }
                } else {
                    if ($object != null) {
                        $apiEntity->parseProduct($object);
                        $apiEntity->setPriceFrom($this->productInfoGetter->getProductPriceString($object, $currency));
                        $result[] = $apiEntity;
                    }
                }
            }
        }

        $result["products"] = $result;
        $result["hits"] = $hits;

        $filters = $JsonResults['facets'];
        $result['filters']['categories'] = [];
        $result['filters']['parameters'] = [];
        if ($filters) {
            if ($filters['categories.id']) {
                $categoriIds = array_keys($filters['categories.id']);
                if ($categoriIds) {
                    $categoriesToFilter = $this->categoryRepository->findBy(['id' => $categoriIds]);
                    $context = [
                        AbstractNormalizer::GROUPS => ['searchable']
                    ];
                    $categories = [];
                    foreach ($categoriesToFilter as $category) {
                        $categories [] = json_decode($this->serializer->serialize($category, "json", $context));
                    }

                    $result['filters']['categories'] = $categories;
                }
            }
            if ($filters['manufacturer']) {

                $parameterIds = $filters['manufacturer'];
                if ($parameterIds) {
                    $parametersToFilter = [];
                    foreach ($parameterIds as $name => $numberOfResults) {
                        $parametersToFilter [] = [
                            'data' => $name,
                            'amountOfResults' => $numberOfResults
                        ];
                    }

                    $result['filters']['parameters'] = $parametersToFilter;

                }
            }

        }

        if ($isJson) {
            $result = $this->serializer->serialize($result, "json");
            return new JsonResponse($result, 200, [], true);
        } else {
            return $result;
        }

    }

}