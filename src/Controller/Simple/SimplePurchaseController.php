<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Symfony\Component\Workflow\Registry;

#[Route('/api-simple/purchases', name: 'simple_purchases_')]
class SimplePurchaseController extends AbstractController
{
    #[Route('/{purchase}/make-transition', name: 'make_transition', methods: ['POST'])]
    public function makePurchaseTransition(Purchase $purchase, Request $request, Registry $registry, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON format');
        }
        if (!isset($data['transition'])) {
            throw new BadRequestHttpException('Missing transition');
        }
        if (!empty($data['package'])) {
            $purchase->setTransportNumber($data['package']);
        }

        $pFlow = $registry->get($purchase);
        if (!$pFlow->getEnabledTransition($purchase, $data['transition'])) {
            throw new BadRequestHttpException('Invalid transition');
        }

        $pFlow->apply($purchase, $data['transition']);

        $em->persist($purchase);
        $em->flush();

        return $this->json(['state' => $purchase->getState()]);
    }


    #[Route('/{purchase}/invoice/pdf', name: 'invoice_download_pdf', methods: ['GET'])]
    public function downloadInvoicePdf(Purchase $purchase, InvoiceMaker $invoiceMaker): Response
    {
        $pdfFilePath = $invoiceMaker->createInvoiceOrProforma($purchase);

        if (!file_exists($pdfFilePath) || !is_readable($pdfFilePath)) {
            throw $this->createNotFoundException('Invoice not found or unreadable');
        }

        return new Response($pdfFilePath, 200, ['Content-Type' => 'text/plain']);
    }

    #[Route('/{purchase}/invoice/xls', name: 'invoice_download_xls', methods: ['GET'])]
    public function downloadInvoiceXls(Purchase $purchase): JsonResponse
    {
        return $this->json(['message' => 'To be implemented']);
    }

    #[Route('/{purchase}/invoice/print', name: 'invoice_download_print', methods: ['GET'])]
    public function downloadInvoicePrint(Purchase $purchase): JsonResponse
    {
        return $this->json(['message' => 'To be implemented']);
    }
}