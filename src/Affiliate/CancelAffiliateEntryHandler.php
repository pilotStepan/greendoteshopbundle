<?php

namespace Greendot\EshopBundle\Affiliate;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;

#[AsMessageHandler]
readonly class CancelAffiliateEntryHandler
{
    public function __construct(
        private AffiliateService   $affiliateService,
        private PurchaseRepository $purchaseRepository,
    ) {}

    public function __invoke(CancelAffiliateEntry $msg): void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);

        if (!$purchase) {
            return;
        }

        $this->affiliateService->cancelAffiliateEntry($purchase);
    }
}