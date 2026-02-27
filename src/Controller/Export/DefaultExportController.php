<?php

namespace Greendot\EshopBundle\Controller\Export;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Export;
use Greendot\EshopBundle\Entity\Project\ExportStatus;
use Greendot\EshopBundle\Message\Export\InitializeExportMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dispatch/export', name: 'greendot_dispatch_export_')]
class DefaultExportController extends AbstractController
{
    #[Route(path:'/{alias}', name: 'default')]
    public function googleProductFeed(
        string $alias,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus
    ): JsonResponse
    {
        $export = new Export();
        $export->setDate(new \DateTime());
        $export->setType($alias);
        $export->setFilename(sprintf('generating_%s_%s.tmp', $alias, time()));

        $status = new ExportStatus();
        $status->setStatus(ExportStatus::CREATED);
        $status->setSuccessCount(0);
        $status->setFailedCount(0);

        $export->setExportStatus($status);

        $entityManager->persist($export);
        $entityManager->flush();

        $messageBus->dispatch(new InitializeExportMessage(
            exportId: $export->getId(),
            alias: $alias
        ));

        return new JsonResponse(sprintf('Export "%s" has been queued,', $alias));
    }
}