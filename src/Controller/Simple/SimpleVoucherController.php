<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Registry;

#[Route('/simple/api/vouchers', name: 'simple_api_vouchers_')]
class SimpleVoucherController extends AbstractController
{
    #[Route(path: '/workflow-places', name: 'workflow_places')]
    public function getVoucherWorkflowPlaces(Registry $registry, Request $request): JsonResponse
    {
        $voucher = new Voucher();
        $pFlow = $registry->get($voucher);
        $places = $pFlow->getDefinition()->getPlaces();
        $placesMetaData = [];
        foreach ($places as $place) {
            $metadataArray = $pFlow->getMetadataStore()->getPlaceMetadata($place);
            $placesMetaData[$place]['desc'] = $metadataArray['description'];
            $placesMetaData[$place]['short_desc'] = $metadataArray['short_description'];
            $placesMetaData[$place]['class'] = $metadataArray['class'];
            $placesMetaData[$place]['simple_color'] = $metadataArray['simple_color'] ?? '#999999';
            $placesMetaData[$place]['name'] = $place;
        }
        return $this->json($placesMetaData, 200);
    }
    #[Route(path: '/workflow-transitions', name: 'workflow_transitions')]
    public function getVoucherWorkflowTransitions(Registry $registry, Request $request): JsonResponse
    {
        $voucher = new Voucher();
        $pFlow = $registry->get($voucher);
        $transitions = $pFlow->getDefinition()->getTransitions();
        return $this->json($transitions, 200);
    }

    #[Route('/{voucher}/make-transition', name: 'make_transition', methods: ['POST'])]
    public function makeVoucherTransition(Voucher $voucher, Request $request, Registry $registry, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON format');
        }
        if (!isset($data['transition'])) {
            throw new BadRequestHttpException('Missing transition');
        }

        $vFlow = $registry->get($voucher);

        if (!$vFlow->can($voucher, $data['transition'])) {
            $blockers = $vFlow->buildTransitionBlockerList($voucher, $data['transition']);
            $messages = array_map(fn($b) => $b->getMessage(), iterator_to_array($blockers));

            return new JsonResponse(['errors' => $messages], Response::HTTP_BAD_REQUEST);
        }

        $vFlow->apply($voucher, $data['transition']);

        $em->persist($voucher);
        $em->flush();

        return $this->json(['state' => $voucher->getState()]);
    }

/* Not needed yet, not remade for voucher

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
*/
}