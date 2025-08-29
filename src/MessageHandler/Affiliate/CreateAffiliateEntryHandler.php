<?php

namespace Greendot\EshopBundle\MessageHandler\Affiliate;

use Greendot\EshopBundle\Message\Affiliate\CreateAffiliateEntry;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\AffiliateService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class CreateAffiliateEntryHandler
{
    public function __construct(
        private AffiliateService    $affiliateService,
        private PurchaseRepository  $purchaseRepository,
    ) {}

    public function __invoke(CreateAffiliateEntry $msg) : void
    {
        $purchase = $this->purchaseRepository->find($msg->purchaseId);
        
        if (!$purchase)
        {
            return;
        }

        $this->affiliateService->createAffiliateEntry($purchase);
    }
}