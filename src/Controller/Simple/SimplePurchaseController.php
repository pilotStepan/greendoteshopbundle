<?php

namespace Greendot\EshopBundle\Controller\Simple;

use JsonException;
use LogicException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Service\ManageVoucher;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Service\ManageClientDiscount;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Greendot\EshopBundle\Entity\Project\ClientDiscount;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Greendot\EshopBundle\Dto\ApplyVoucherOrDiscountRequest;
use Greendot\EshopBundle\Entity\Project\PurchaseDiscussion;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Greendot\EshopBundle\Message\Notification\PurchaseDiscussionEmail;

#[Route('/simple/api/purchases', name: 'simple_api_purchases_')]
class SimplePurchaseController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface    $messageBus,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route(path: '/workflow-places', name: 'workflow_places')]
    public function getPurchaseWorkflowPlaces(Registry $registry, Request $request): JsonResponse
    {
        $purchase = new Purchase();
        $pFlow = $registry->get($purchase);
        $places = $pFlow->getDefinition()->getPlaces();
        $placesMetaData = [];
        foreach ($places as $place) {
            $metadataArray = $pFlow->getMetadataStore()->getPlaceMetadata($place);
            $placesMetaData[$place]['desc'] = $metadataArray['description'];
            $placesMetaData[$place]['short_desc'] = $metadataArray['short_description'];
            $placesMetaData[$place]['icon'] = $metadataArray['icon'];
            $placesMetaData[$place]['simple_color'] = $metadataArray['simple_color'] ?? '#999999';
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
        } catch (JsonException) {
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

    #[Route('/{purchase}/send-message', name: 'send_message', methods: ['POST'])]
    public function sendMessage(Purchase $purchase, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid JSON format');
        }

        if (!isset($data['content'])) {
            throw new BadRequestHttpException('Missing content');
        }

        $discussion = (new PurchaseDiscussion())
            ->setPurchase($purchase)
            ->setContent($data['content'])
            ->setIsAdmin(true)
            ->setIsRead(false)
        ;

        $this->em->persist($discussion);
        $this->em->flush();

        $this->messageBus->dispatch(
            new PurchaseDiscussionEmail($purchase->getId(), $data['content']),
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }


    #[Route('/{purchase}/invoice/pdf', name: 'invoice_download_pdf', methods: ['GET'])]
    public function downloadInvoicePdf(Purchase $purchase, InvoiceMaker $invoiceMaker): BinaryFileResponse
    {
        $pdfFilePath = $invoiceMaker->createInvoiceOrProforma($purchase);

        if (!file_exists($pdfFilePath) || !is_readable($pdfFilePath)) {
            throw $this->createNotFoundException('Invoice not found or unreadable');
        }

        $response = new BinaryFileResponse($pdfFilePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'invoice_' . $purchase->getId() . '.pdf',
        );
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
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

    #[Route('/{purchase}/apply-voucher', name: 'apply_voucher', methods: ['POST'])]
    public function applyVoucherToPurchase(
        Purchase                                           $purchase,
        #[MapRequestPayload] ApplyVoucherOrDiscountRequest $payload,
        EntityManagerInterface                             $em,
        ManageVoucher                                      $manageVoucher,
    ): JsonResponse
    {
        $voucher = $em->getRepository(Voucher::class)->findOneBy(['hash' => $payload->hash]);
        if (!$voucher) {
            return $this->json(['success' => false, 'message' => 'Voucher nenalezen'], Response::HTTP_NOT_FOUND);
        }

        try {
            $manageVoucher->use($voucher, $purchase);
        } catch (LogicException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'success' => true,
            'purchaseId' => $purchase->getId(),
            'hash' => $voucher->getHash(),
            'voucherState' => $voucher->getState(),
        ], Response::HTTP_OK);
    }

    #[Route('/{purchase}/apply-client-discount', name: 'apply_client_discount', methods: ['POST'])]
    public function applyClientDiscountToPurchase(
        Purchase                                           $purchase,
        #[MapRequestPayload] ApplyVoucherOrDiscountRequest $payload,
        EntityManagerInterface                             $em,
        ManageClientDiscount                               $manageClientDiscount,
    ): JsonResponse
    {
        $clientDiscount = $em->getRepository(ClientDiscount::class)->findOneBy(['hash' => $payload->hash]);

        try {
            $manageClientDiscount->use($clientDiscount, $purchase);
        } catch (LogicException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'success' => true,
            'purchaseId' => $purchase->getId(),
            'hash' => $clientDiscount->getHash(),
        ], Response::HTTP_OK);
    }
}
