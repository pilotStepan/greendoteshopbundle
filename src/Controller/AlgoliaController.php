<?php

namespace Greendot\EshopBundle\Controller;


use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/* TODO: discuss usage */
class AlgoliaController extends AbstractController
{
    public function __construct(
        private ProductRepository        $productRepository,
        private ProductVariantRepository $productVariantRepository,
        private ManagerRegistry          $managerRegistry,
        private SerializerInterface      $serializer
    ) {}

    #[Route('/api/search-categories', name: 'api_search_categories', methods: ['GET'])]
    public function searchCategories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $query = $request->query->get('q', '');

        $regularCategories = $categoryRepository->findByNameLike($query, 3);
        $specialCategories = $categoryRepository->findByNameLikeAndType($query, 6, 3);

        $results = [
            'regularCategories' => $regularCategories,
            'specialCategories' => $specialCategories,
        ];

        $json = $this->serializer->serialize($results, 'json', [
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            },
            'max_depth_handler'          => function ($object) {
                return $object->getId();
            },
        ]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/api/algolia/search/{query}', name: 'search_algolia', defaults: ['hits' => 6, 'page' => 0])]
    #[Route('/api/algolia/search/{query}/max-hits-{hits}/page-{page}', name: 'search_algolia_parameters')]
    public function search(
        string           $query,
        int              $hits,
        int              $page,
        SessionInterface $session,
        Request          $request
    ): JsonResponse
    {
        $filters  = json_decode($request->getContent(), true) ?? [];
        $currency = $session->get('selectedCurrency');

        return $this->json($this->algoliaSearch->search($query, $currency, $hits, $page, true, $filters));
    }

    #[Route('/algolia/products', name: 'algolia_product_import')]
    public function indexProducts(Request $request): RedirectResponse
    {
        return $this->indexEntities($request, 'product');
    }

    #[Route('/algolia/variants', name: 'algolia_variants')]
    public function indexVariants(Request $request): RedirectResponse
    {
        return $this->indexEntities($request, 'variant');
    }

    private function indexEntities(Request $request, string $type): RedirectResponse
    {
        set_time_limit(0);

        $offset = $request->query->getInt('offset');
        $limit  = $request->query->getInt('limit');

        if ($offset === 0 && $limit === 0) {
            throw $this->createNotFoundException('Limit and offset parameters are required.');
        }

        $repository = $type === 'product' ? $this->productRepository : $this->productVariantRepository;
        $routeName  = $type === 'product' ? 'algolia_product_import' : 'algolia_variants';

        $entities = $repository->findAllWithLimit($limit, $offset);

        if (empty($entities)) {
            return $this->redirectToRoute('homepage');
        }

        //$this->searchService->index($this->managerRegistry->getManager(), $entities);

        if (count($entities) < $limit) {
            return $this->redirectToRoute('homepage');
        }

        return $this->redirectToRoute($routeName, [
            'offset' => $offset + $limit,
            'limit'  => $limit
        ]);
    }
}
