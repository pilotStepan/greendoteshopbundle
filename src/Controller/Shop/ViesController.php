<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Service\Vies\ManageVies;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/vies', name: 'api_vies_')]
class ViesController extends AbstractController
{
    public function __construct(
        private readonly ManageVies $manageVies,
    ) {}

    #[Route('/vat/{vat}', name: 'vat', methods: ['GET'])]
    public function getVatInfo(Request $request): JsonResponse
    {
        $rawVat = $request->attributes->get('vat', '');
        if (empty($rawVat)) {
            return new JsonResponse(['statusText' => 'VAT ID is required'], 400);
        }
        try {
            $result = $this->manageVies->getVatInfo($rawVat);
            return new JsonResponse($result);
        } catch (Exception $e) {
            return new JsonResponse(['statusText' => 'Chyba při ověřování DIČ'], 500);
        }
    }
}