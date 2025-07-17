<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route('/simple/api/purchases', name: 'simple_api_purchases_')]
class SimplePurchaseController extends AbstractController
{
    #[Route(path: '/workflow-places', name: 'workflow_places')]
    public function getPurchaseWorkflowPlaces(Registry $registry, Request $request): JsonResponse
    {
        $purchase = new Purchase();
        $pFlow = $registry->get($purchase);
        $places = $pFlow->getDefinition()->getPlaces();
        $placesMetaData = [];
        foreach ($places as $place) {
            $placesMetaData[$place]['desc'] = $pFlow->getMetadataStore()->getPlaceMetadata($place)['description'];
            $placesMetaData[$place]['short_desc'] = $pFlow->getMetadataStore()->getPlaceMetadata($place)['short_description'];
            $placesMetaData[$place]['icon'] = $pFlow->getMetadataStore()->getPlaceMetadata($place)['icon'];
            $placesMetaData[$place]['name'] = $place;
        }
        return $this->json($placesMetaData, 200);
    }

    #[Route(path: '/workflow-transitions', name: 'workflow_transitions')]
    public function getPurchaseWorkflowTransitions(Registry $registry, Request $request): JsonResponse
    {
        $purchase = new Purchase();
        $pFlow = $registry->get($purchase);
        $transitions = $pFlow->getDefinition()->getTransitions();
        return $this->json($transitions, 200);
    }

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

        $pFlow = $registry->get($purchase);

        if (!$pFlow->can($purchase, $data['transition'])) {
            $blockers = $pFlow->buildTransitionBlockerList($purchase, $data['transition']);
            $messages = array_map(fn($b) => $b->getMessage(), iterator_to_array($blockers));

            return new JsonResponse(['errors' => $messages], Response::HTTP_BAD_REQUEST);
        }

        // We wrap it in transaction to ensure atomicity
        $newState = $em->wrapInTransaction(function () use ($purchase, $data, $pFlow) {
            // Mutate the aggregate
            if (!empty($data['package'])) {
                $purchase->setTransportNumber($data['package']);
            }

            // Add a runtime “silent” flag if Simple sets it
            $pFlow->apply($purchase, $data['transition'], [
                'silent' => $data['silent'] ?? false,
            ]);

            // no need for persist(), purchase is already managed
            return $purchase->getState();
        });

        return $this->json(['state' => $newState]);
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