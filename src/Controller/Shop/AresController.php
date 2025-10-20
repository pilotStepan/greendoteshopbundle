<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Greendot\EshopBundle\Service\AresService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class AresController extends AbstractController
{
    public function __construct(private readonly AresService $aresService)
    {
    }

    #[CustomApiEndpoint]
    #[Route('/api/ares/company/{ic}', name: 'api_ares_company', methods: ['GET'])]
    public function getCompanyInfo(string $ic): JsonResponse
    {
        $result = $this->aresService->fetchCompanyByIc(trim($ic));

        if (isset($result['error'])) {
            return new JsonResponse(['statusText' => $result['error']], 400);
        }

        return new JsonResponse($result['data']);
    }

    #[CustomApiEndpoint]
    #[Route('/api/ares/addresses', name: 'api_ares_addresses', methods: ['GET'])]
    public function getAddresses(Request $request): JsonResponse
    {
        $street = $request->query->get('street', '');
        $city = $request->query->get('city');
        $zip = $request->query->get('zip');
        $start = $request->query->getInt('start', 0);
        $itemCount = $request->query->getInt('itemCount', 8);

        try {
            $result = $this->aresService->searchAddresses($street, $city, $zip, $start, $itemCount);
            return new JsonResponse($result);
        } catch (ExceptionInterface $e) {
            return new JsonResponse(
                ['statusText' => 'Nepodařilo se načíst data z ARES'],
                400
            );
        }
    }
}