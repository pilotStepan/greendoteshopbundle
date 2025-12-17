<?php

namespace Greendot\EshopBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Doctrine\ORM\Events;
use Greendot\EshopBundle\Service\ListenerManager;
use Greendot\EshopBundle\Service\Price\CalculatedPricesService;

#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Purchase::class)]
class PurchaseEventListener
{
    public function __construct(
        private CalculatedPricesService $calculatedPricesService,
        private ListenerManager         $listenerManager,
    ) {}

    public function postLoad(Purchase $purchase, PostLoadEventArgs $event): void
    { 
        if (!$this->supports())
        {
            return;
        }

        $this->calculatedPricesService->makeCalculatedPricesForPurchase($purchase);
    }

    public function supports() : bool
    {
        return !$this->listenerManager->isDisabled(self::class);
    }
}
