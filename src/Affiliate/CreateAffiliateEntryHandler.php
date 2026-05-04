<?php

namespace Greendot\EshopBundle\Affiliate;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;

#[AsMessageHandler]
class CreateAffiliateEntryHandler
{
    public function __construct(
        private AffiliateService   $affiliateService,
        private PurchaseRepository $purchaseRepository,
    ) {}

    public function __invoke(CreateAffiliateEntry $msg): void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);

        if (!$purchase) {
            return;
        }

        $this->affiliateService->createAffiliateEntry($purchase);
    }
}