<?php

namespace Greendot\EshopBundle\Service\DownloadRequests;

use Doctrine\Persistence\ManagerRegistry;
use Greendot\EshopBundle\Entity\Project\DownloadRequest;
use Greendot\EshopBundle\Service\ManageMails;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DefaultDownloadRequestHandler implements DownloadRequestHandlerInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ManageMails $manageMails,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir
    ){}

    public function supports(DownloadRequest $downloadRequest): bool
    {
        return true;
    }

    public function approve(DownloadRequest $downloadRequest): void
    {
        $mail = $this->manageMails->getBaseTemplate();
        $uploadPath = $downloadRequest?->getUpload()?->getPath();
        $uploadPath = $this->publicDir.str_replace( '\\', '/', $uploadPath);
        ($mail)
            ->to($downloadRequest->getEmail())
            ->htmlTemplate('/email/download-requests/approve-message.html.twig')
            ->context(['downloadRequest' => $downloadRequest])
            ->subject('Vyžádaný soubor')
            ->attachFromPath($uploadPath)
        ;
        $this->manageMails->sendTemplate($mail);

        $manager = $this->managerRegistry->getManager();
        $downloadRequest->setIsApproved(1);
        $downloadRequest->setIsCompleted(1);
        $manager->persist($downloadRequest);
        $manager->flush();

    }

    public function decline(DownloadRequest $downloadRequest): void
    {
        $manager = $this->managerRegistry->getManager();
        $downloadRequest->setIsApproved(0);
        $downloadRequest->setIsCompleted(1);
        $manager->persist($downloadRequest);
        $manager->flush();
    }
}