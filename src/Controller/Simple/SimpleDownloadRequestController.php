<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Greendot\EshopBundle\Entity\Project\DownloadRequest;
use Greendot\EshopBundle\Service\DownloadRequests\DownloadRequestProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/simple/api/download-request', name: 'simple_api_download_request_')]
class SimpleDownloadRequestController extends AbstractController
{
    public function __construct(
        private readonly DownloadRequestProvider $downloadRequestProvider
    ){}

    #[Route('/decline/{id}', name: 'decline')]
    public function decline(DownloadRequest $downloadRequest): JsonResponse
    {
        $handler = $this->downloadRequestProvider->get($downloadRequest);
        $handler->decline($downloadRequest);
        return new JsonResponse([]);
    }

    #[Route('/approve/{id}', name:'approve')]
    public function approve(DownloadRequest $downloadRequest): JsonResponse
    {
        $handler = $this->downloadRequestProvider->get($downloadRequest);
        $handler->approve($downloadRequest);
        return new JsonResponse([]);
    }
}