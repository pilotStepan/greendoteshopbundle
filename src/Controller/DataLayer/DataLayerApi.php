<?php

namespace Greendot\EshopBundle\Controller\DataLayer;

use Greendot\EshopBundle\Service\DataLayer\DataLayerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DataLayerApi extends AbstractController
{
    #[Route('/gtm/read/all', name:'gtm_read_all')]
    public function getAll(DataLayerManager $dataLayerManager): JsonResponse
    {
        return new JsonResponse($dataLayerManager->all(), 200);
    }
}