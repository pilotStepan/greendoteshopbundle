<?php

namespace Greendot\EshopBundle\Service\DownloadRequests;

use Greendot\EshopBundle\Entity\Project\DownloadRequest;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.download_request')]
interface DownloadRequestHandlerInterface
{
    public function supports(DownloadRequest $downloadRequest): bool;
    public function approve(DownloadRequest $downloadRequest): void;
    public function decline(DownloadRequest $downloadRequest): void;

}