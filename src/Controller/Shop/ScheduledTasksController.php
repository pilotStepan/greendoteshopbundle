<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Service\PaymentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Greendot\EshopBundle\Service\Imports\Branch\ManageBranch;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ScheduledTasksController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository      $productRepository,
        private readonly PaymentService         $paymentService,
        private readonly ManageBranch           $manageBranch,
    ) {}

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

    #[Route('/scheduled/bank/{startDate}', name: 'scheduled_bank', methods: ['GET'])]
    public function scheduleBank(string $startDate): JsonResponse
    {
        $date = \DateTime::createFromFormat('d.m.Y', $startDate);
        if (!$date || $date->format('d.m.Y') !== $startDate) {
            return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentService->downloadAndProcessPayments($date);

        return $this->json(['message' => 'Payments processed successfully']);
    }

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
}
