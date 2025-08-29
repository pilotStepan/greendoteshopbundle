<?php

namespace Greendot\EshopBundle\MessageHandler\Affiliate;

use Greendot\EshopBundle\Message\Affiliate\CancelAffiliateEntry;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class CancelAffiliateEntryHandler
{
    public function __construct(
        private AffiliateService    $affiliateService,
        private PurchaseRepository  $purchaseRepository,
    ) {}

    public function __invoke(CancelAffiliateEntry $msg) : void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);

        if (!$purchase)
        {
            return;
        }
        
        $this->affiliateService->cancelAffiliateEntry($purchase);
    }
}