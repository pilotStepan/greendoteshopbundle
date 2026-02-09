<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Entity\Project\Voucher;
use Greendot\EshopBundle\Service\CertificateMaker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

    #[Route('/{voucher}/download', name: 'download', methods: ['GET'])]
    public function downloadVoucher(Voucher $voucher, CertificateMaker $certificateMaker): Response
    {
        $pdfContent = $certificateMaker->createCertificate($voucher);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_ATTACHMENT . '; filename="darkovy_certifikat_' . $voucher->getId() . '.pdf"',
        ]);
    }
}