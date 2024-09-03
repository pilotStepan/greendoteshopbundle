<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Repository\Project\ProductRepository;

class ScheduledTasksController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ProductRepository $productRepository;
    private PaymentService $paymentService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProductRepository      $productRepository,
        PaymentService         $paymentService
    )
    {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
        $this->paymentService = $paymentService;
    }

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
                    'sold_amount' => (int)$soldProduct['sold_amount']
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
}
