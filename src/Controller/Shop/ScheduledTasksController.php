<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Greendot\EshopBundle\Algolia\ProductIndexerInterface;
use Greendot\EshopBundle\Service\Imports\Branch\ManageBranch;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Greendot\EshopBundle\Payment\RbBank\RbBankPaymentImportService;

class ScheduledTasksController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface     $entityManager,
        private readonly ProductRepository          $productRepository,
        private readonly RbBankPaymentImportService $rbBankPaymentImportService,
        private readonly ManageBranch               $manageBranch,
    ) {}

    #[CustomApiEndpoint]
    #[Route('/scheduled/sales', name: 'scheduled_sales', methods: ['GET'])]
    public function scheduleSales(): JsonResponse
    {
        $startDate = (new \DateTime())->modify('-6 months');
        $endDate = new \DateTime();

        $soldProducts = $this->productRepository->getSoldProductsCount($startDate, $endDate);
        $result = [];

        foreach ($soldProducts as $soldProduct) {
            $product = $this->productRepository->find($soldProduct['id']);
            if ($product) {
                $product->setSoldAmount($soldProduct['sold_amount']);
                $this->entityManager->persist($product);

                $result[] = [
                    'product_id' => (int)$soldProduct['id'],
                    'sold_amount' => (int)$soldProduct['sold_amount'],
                ];
            }
        }

        $this->entityManager->flush();

        return $this->json($result);
    }

    #[CustomApiEndpoint]
    #[Route('/scheduled/bank', name: 'scheduled_bank', methods: ['GET'])]
    public function scheduleBank(): JsonResponse
    {
        $date = new \DateTime();
        $date->modify("-3 days");

        $this->rbBankPaymentImportService->downloadAndProcessPayments($date);

        return $this->json(['message' => 'Payments processed successfully']);
    }

    #[CustomApiEndpoint]
    #[Route('/scheduled/czechpost', name: 'scheduled_czechpost', methods: ['GET'])]
    public function importCzechPost(): JsonResponse
    {
        $this->manageBranch->importCzechPost();
        return $this->json(['message' => 'Pobočky České pošty byly úspěšně importovány']);
    }

    #[Route('/scheduled/packeta', name: 'scheduled_packeta', methods: ['GET'])]
    public function importPacketa(): JsonResponse
    {
        $this->manageBranch->importPacketa();
        return $this->json(['message' => 'Pobočky Zásilkovny byly úspěšně importovány']);
    }

    #[Route('/scheduled/algolia-reindex-products', name: 'scheduled_algolia_reindex_products', methods: ['GET'])]
    public function algoliaReindexProducts(
        Request                 $request,
        ProductIndexerInterface $indexer,
        LockFactory             $lockFactory,
        LoggerInterface         $logger,
    ): JsonResponse
    {
        set_time_limit(0);

        $lock = $lockFactory->createLock('algolia_reindex_products', 3600);
        if (!$lock->acquire()) {
            return new JsonResponse(['status' => 'already_running'], 409);
        }

        try {
            $result = $indexer->reindex($request->query->get('index'));

            return new JsonResponse(['status' => 'ok'] + $result->toArray());
        } catch (\Throwable $e) {
            $logger->error('Algolia reindex failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        } finally {
            $lock->release();
        }
    }
}
