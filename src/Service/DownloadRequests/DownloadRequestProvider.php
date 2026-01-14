<?php

namespace Greendot\EshopBundle\Service\DownloadRequests;

use Greendot\EshopBundle\Entity\Project\DownloadRequest;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class DownloadRequestProvider
{

    private iterable $downloadRequestHandlers;

    public function __construct(
        #[AutowireIterator('app.download_request')]
        iterable $downloadRequestHandlers
    )
    {
        $this->downloadRequestHandlers = $downloadRequestHandlers;
    }

    public function get(DownloadRequest $downloadRequest): DownloadRequestHandlerInterface
    {
        foreach ($this->downloadRequestHandlers as $handler){
            assert($handler instanceof DownloadRequestHandlerInterface);
            if ($handler->supports($downloadRequest)) return $handler;
        }
        throw new \Exception('No DownloadRequestHandler found');
    }

}