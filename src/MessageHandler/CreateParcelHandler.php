<?php

namespace Greendot\EshopBundle\MessageHandler;

use RuntimeException;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Message\CreateParcelMessage;
use Greendot\EshopBundle\Service\ManagePurchase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;

#[AsMessageHandler]
readonly class CreateParcelHandler
{
    public function __construct(
        private ParcelServiceProvider  $parcelServiceProvider,
        private PurchaseRepository     $purchaseRepository,
        private ManagePurchase         $managePurchase,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(CreateParcelMessage $msg): void
    {
        $purchaseId = $msg->purchaseId;
        $purchase = $this->purchaseRepository->find($purchaseId);

        if (!$purchase) {
            throw new RuntimeException('Purchase not found for ID: ' . $purchaseId);
        }

        $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
        if (!$parcelService) return; // No parcel service found, so don't process

        // Prepare prices for the purchase before creating the parcel
        $this->managePurchase->preparePrices($purchase);

        // Let it explode, so the message can be retried
        $parcelId = $parcelService->createParcel($purchase);
        $purchase->setTransportNumber($parcelId);

        $this->em->flush();
    }
}